<?php

declare(strict_types=1);

namespace staabm\PHPStanDba\Extensions;

use Composer\InstalledVersions;
use Composer\Semver\VersionParser;
use Doctrine\DBAL\Connection;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\MixedType;
use PHPStan\Type\Type;
use staabm\PHPStanDba\DoctrineReflection\DoctrineReflection;
use staabm\PHPStanDba\QueryReflection\QueryReflection;

final class DoctrineConnectionFetchDynamicReturnTypeExtension implements DynamicMethodReturnTypeExtension
{
    private const METHODS = [
        'fetchone',
        'fetchfirstcolumn',
        'fetchassociative',
        'fetchallassociative',
        'fetchnumeric',
        'fetchallnumeric',
        'fetchallkeyvalue',
        'iteratecolumn',
        'iterateassociative',
        'iteratenumeric',
        'iteratekeyvalue',
    ];

    public function getClass(): string
    {
        return Connection::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return \in_array(strtolower($methodReflection->getName()), self::METHODS, true);
    }

    public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): Type
    {
        $args = $methodCall->getArgs();
        $defaultReturn = ParametersAcceptorSelector::selectFromArgs(
            $scope,
            $methodCall->getArgs(),
            $methodReflection->getVariants(),
        )->getReturnType();

        if (\count($args) < 1) {
            return $defaultReturn;
        }

        if ($scope->getType($args[0]->value) instanceof MixedType) {
            return $defaultReturn;
        }

        // make sure we don't report wrong types in doctrine 2.x
        if (!InstalledVersions::satisfies(new VersionParser(), 'doctrine/dbal', '3.*')) {
            return $defaultReturn;
        }

        $params = null;
        if (\count($args) > 1) {
            $params = $args[1]->value;
        }

        $resultType = $this->inferType($methodReflection, $args[0]->value, $params, $scope);
        if (null !== $resultType) {
            return $resultType;
        }

        return $defaultReturn;
    }

    private function inferType(MethodReflection $methodReflection, Expr $queryExpr, ?Expr $paramsExpr, Scope $scope): ?Type
    {
        $queryReflection = new QueryReflection();
        $doctrineReflection = new DoctrineReflection();

        if (null === $paramsExpr) {
            $queryStrings = $queryReflection->resolveQueryStrings($queryExpr, $scope);
        } else {
            $parameterTypes = $scope->getType($paramsExpr);
            $queryStrings = $queryReflection->resolvePreparedQueryStrings($queryExpr, $parameterTypes, $scope);
        }

        return $doctrineReflection->createFetchType($queryStrings, $methodReflection);
    }
}
