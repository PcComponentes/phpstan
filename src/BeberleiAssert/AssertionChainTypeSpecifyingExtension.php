<?php
declare(strict_types=1);

namespace PcComponentes\PHPStan\BeberleiAssert;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp\BooleanAnd;
use PhpParser\Node\Expr\BinaryOp\BooleanOr;
use PhpParser\Node\Expr\BinaryOp\Identical;
use PhpParser\Node\Expr\BinaryOp\NotIdentical;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Analyser\SpecifiedTypes;
use PHPStan\Analyser\TypeSpecifier;
use PHPStan\Analyser\TypeSpecifierAwareExtension;
use PHPStan\Analyser\TypeSpecifierContext;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\BeberleiAssert\AssertHelper;
use PHPStan\Type\MethodTypeSpecifyingExtension;

final class AssertionChainTypeSpecifyingExtension implements MethodTypeSpecifyingExtension, TypeSpecifierAwareExtension
{
    private TypeSpecifier $typeSpecifier;

    public function setTypeSpecifier(TypeSpecifier $typeSpecifier): void
    {
        $this->typeSpecifier = $typeSpecifier;
    }

    public function getClass(): string
    {
        return 'Assert\AssertionChain';
    }

    public function isMethodSupported(
        MethodReflection $methodReflection,
        MethodCall $node,
        TypeSpecifierContext $context,
    ): bool {
        return null !== $this->extractOperations($node);
    }

    public function specifyTypes(
        MethodReflection $methodReflection,
        MethodCall $node,
        Scope $scope,
        TypeSpecifierContext $context,
    ): SpecifiedTypes {
        $operations = $this->extractOperations($node);

        if (null === $operations) {
            return new SpecifiedTypes();
        }

        $specifiedTypes = new SpecifiedTypes();
        $currentExpression = null;
        $allMode = false;
        $nullOrMode = false;

        foreach ($operations as $operation) {
            if ('that' === $operation['name']) {
                if (!isset($operation['args'][0])) {
                    continue;
                }

                $currentExpression = $operation['args'][0]->value;
                $allMode = false;
                $nullOrMode = false;

                continue;
            }

            if (null === $currentExpression) {
                continue;
            }

            if ('all' === $operation['name']) {
                $allMode = true;

                continue;
            }

            if ('nullOr' === $operation['name']) {
                $nullOrMode = true;

                continue;
            }

            if ('setAssertionClassName' === $operation['name']) {
                continue;
            }

            $arguments = [new Arg($currentExpression), ...$operation['args']];
            $currentSpecifiedTypes = null;

            if ($this->isCustomStringAssertion($operation['name'])) {
                $currentSpecifiedTypes = $this->specifyStringAssertion(
                    $scope,
                    $currentExpression,
                    $nullOrMode,
                    'base64' !== $operation['name'],
                );
            } else {
                if ($allMode && $this->isSupportedAllNotAssertion($operation['name'])) {
                    // @phpstan-ignore phpstanApi.method
                    $currentSpecifiedTypes = AssertHelper::handleAllNot(
                        $this->typeSpecifier,
                        $scope,
                        $operation['name'],
                        $arguments,
                    );
                } else {
                    // @phpstan-ignore phpstanApi.method
                    $isSupported = AssertHelper::isSupported($operation['name'], $arguments);

                    if ($isSupported) {
                        // @phpstan-ignore phpstanApi.method
                        $currentSpecifiedTypes = AssertHelper::specifyTypes(
                            $this->typeSpecifier,
                            $scope,
                            $operation['name'],
                            $arguments,
                            $nullOrMode,
                        );

                        if ($allMode) {
                            // @phpstan-ignore phpstanApi.method
                            $currentSpecifiedTypes = AssertHelper::handleAll(
                                $this->typeSpecifier,
                                $scope,
                                $currentSpecifiedTypes,
                            );
                        }
                    }
                }
            }

            if (null === $currentSpecifiedTypes) {
                continue;
            }

            $specifiedTypes = $specifiedTypes->unionWith($currentSpecifiedTypes);
        }

        return $specifiedTypes;
    }

    /** @return list<array{name: string, args: list<Arg>}>|null */
    private function extractOperations(Expr $expression): ?array
    {
        if ($expression instanceof StaticCall) {
            return $this->isThatFactoryCall($expression) ? [[
                'name' => 'that',
                'args' => \array_values($expression->getArgs()),
            ]] : null;
        }

        if (!$expression instanceof MethodCall || !$expression->name instanceof Identifier) {
            return null;
        }

        $operations = $this->extractOperations($expression->var);

        if (null === $operations) {
            return null;
        }

        $operations[] = [
            'name' => $expression->name->toString(),
            'args' => \array_values($expression->getArgs()),
        ];

        return $operations;
    }

    private function isThatFactoryCall(StaticCall $expression): bool
    {
        if (!$expression->class instanceof Name || !$expression->name instanceof Identifier) {
            return false;
        }

        if ('that' !== $expression->name->toString()) {
            return false;
        }

        $className = \ltrim($expression->class->toString(), '\\');

        return 'Assert' === $className || 'Assert\\Assert' === $className;
    }

    private function isSupportedAllNotAssertion(string $assertion): bool
    {
        return \in_array($assertion, ['notBlank', 'notIsInstanceOf', 'notNull', 'notSame'], true);
    }

    private function isCustomStringAssertion(string $assertion): bool
    {
        return \in_array($assertion, ['base64', 'date', 'uuid'], true);
    }

    private function specifyStringAssertion(
        Scope $scope,
        Expr $expression,
        bool $nullOrMode,
        bool $nonEmpty,
    ): SpecifiedTypes {
        $assertionExpression = new FuncCall(
            new Name('is_string'),
            [new Arg($expression)],
        );

        if ($nonEmpty) {
            $assertionExpression = new BooleanAnd(
                $assertionExpression,
                new NotIdentical($expression, new String_('')),
            );
        }

        if ($nullOrMode) {
            $assertionExpression = new BooleanOr(
                new Identical($expression, new ConstFetch(new Name('null'))),
                $assertionExpression,
            );
        }

        return $this->typeSpecifier->specifyTypesInCondition(
            $scope,
            $assertionExpression,
            TypeSpecifierContext::createTruthy(),
        );
    }
}
