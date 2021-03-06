<?php

namespace Phpactor\Completion\Bridge\TolerantParser\WorseReflection;

use Microsoft\PhpParser\Node;
use Microsoft\PhpParser\Node\Expression\AssignmentExpression;
use Phpactor\WorseReflection\Core\Inference\Frame;
use Phpactor\WorseReflection\Core\Inference\Variable;
use Phpactor\WorseReflection\Reflector;

abstract class AbstractVariableCompletor
{
    /**
     * @var Reflector
     */
    private $reflector;

    public function __construct(Reflector $reflector)
    {
        $this->reflector = $reflector;
    }

    /**
     * @return Variable[]
     */
    protected function variableCompletions(Node $node, string $source, int $offset): array
    {
        $partialSource = mb_substr($source, 0, $offset);

        $dollarPosition = strrpos($partialSource, '$');
        if (false === $dollarPosition) {
            return [];
        }

        $partialMatch = mb_substr($partialSource, $dollarPosition);

        $offset = $this->offsetToReflect($node, $offset);
        $reflectionOffset = $this->reflector->reflectOffset($source, $offset);
        $frame = $reflectionOffset->frame();

        // Get all declared variables up until the offset. The most
        // recently declared variables should be first (which is why
        // we reverse the array).
        $reversedLocals = $this->orderedVariablesUntilOffset($frame, $offset);

        // Ignore variables that have already been suggested.
        $seen = [];
        $variables = [];

        /** @var Variable $local */
        foreach ($reversedLocals as $local) {
            if (isset($seen[$local->name()])) {
                continue;
            }

            $name = ltrim($partialMatch, '$');
            $matchPos = -1;

            if ($name) {
                $matchPos = mb_strpos($local->name(), $name);
            }

            if ('$' !== $partialMatch && 0 !== $matchPos) {
                continue;
            }

            $seen[$local->name()] = true;
            $variables[] = $local;
        }

        return $variables;
    }

    private function offsetToReflect(Node $node, int $offset)
    {
        $parentNode = $node->parent;
        
        // If the parent is an assignment expression, then only parse
        // until the start of the expression, not the start of the variable
        // under completion:
        //
        //     $left = $lef<>
        //
        // Otherwise $left will be evaluated to <unknown>.
        if ($parentNode instanceof AssignmentExpression) {
            $offset = $parentNode->getFullStart();
        }
        return $offset;
    }

    private function orderedVariablesUntilOffset(Frame $frame, int $offset)
    {
        return array_reverse(iterator_to_array($frame->locals()->lessThanOrEqualTo($offset)));
    }
}
