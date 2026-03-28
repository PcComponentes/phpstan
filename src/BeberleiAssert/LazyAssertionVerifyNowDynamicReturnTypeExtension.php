<?php
declare(strict_types=1);

namespace PcComponentes\PHPStan\BeberleiAssert;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\BooleanType;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Type;

final class LazyAssertionVerifyNowDynamicReturnTypeExtension implements DynamicMethodReturnTypeExtension
{
    public function getClass(): string
    {
        return 'Assert\LazyAssertion';
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return 'verifyNow' === $methodReflection->getName();
    }

    public function getTypeFromMethodCall(
        MethodReflection $methodReflection,
        MethodCall $methodCall,
        Scope $scope,
    ): Type {
        return new BooleanType();
    }
}
