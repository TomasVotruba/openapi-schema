<?php

namespace Lang\OpenApiDefinition;

use Amateri\PropertyReader\PropertyReaderInterface;
use Amateri\PropertyReader\VariableType\ArrayVariableType;
use Amateri\PropertyReader\VariableType\ClassVariableType;
use Amateri\PropertyReader\VariableType\MixedVariableType;
use Amateri\PropertyReader\VariableType\ScalarVariableType;
use Amateri\PropertyReader\VariableType\UnionVariableType;
use Amateri\PropertyReader\VariableType\VariableTypeInterface;
use Lang\OpenApiDefinition\ValueSchema\Builder\ArraySchemaBuilder;
use Lang\OpenApiDefinition\ValueSchema\Builder\BooleanSchemaBuilder;
use Lang\OpenApiDefinition\ValueSchema\Builder\FloatSchemaBuilder;
use Lang\OpenApiDefinition\ValueSchema\Builder\HashmapSchemaBuilder;
use Lang\OpenApiDefinition\ValueSchema\Builder\IntegerSchemaBuilder;
use Lang\OpenApiDefinition\ValueSchema\Builder\ObjectSchemaBuilder;
use Lang\OpenApiDefinition\ValueSchema\Builder\StringSchemaBuilder;
use Lang\OpenApiDefinition\ValueSchema\ObjectSchema;
use Lang\OpenApiDefinition\ValueSchema\ValueSchemaInterface;

/**
 * @TODO: probably new name
 */
final class SchemaParser
{
    private PropertyReaderInterface $propertyReader;

    public function __construct(PropertyReaderInterface $propertyReader)
    {
        $this->propertyReader = $propertyReader;
    }

    public function getEntitySchema(string $class): ObjectSchema
    {
        $builder = $this->getClassSchemaBuilder($class);
        return $builder->build();
    }

    private function getClassSchemaBuilder(string $class): ObjectSchemaBuilder
    {
        if (!class_exists($class)) {
            throw new \Exception("TODO");
        }

        $reflection = new \ReflectionClass($class);

        $propertiesSchemas = [];
        foreach ($reflection->getProperties() as $propertyReflection) {
            // if property is not public, then skip it.
            if (!$propertyReflection->isPublic()) {
                continue;
            }

            $propertiesSchemas[$propertyReflection->getName()] = $this->getPropertySchema($propertyReflection);
        }
        return (new ObjectSchemaBuilder())->withPropertiesSchemas($propertiesSchemas);
    }

    private function getPropertySchema(\ReflectionProperty $propertyReflection): ValueSchemaInterface
    {
        $variableType = $this->propertyReader->readUnifiedVariableType($propertyReflection);
        $annotations = []; // TODO
        return $this->translate($variableType, $annotations);
    }

    private function translate(?VariableTypeInterface $variableType, array $annotations): ValueSchemaInterface
    {
        if ($variableType === null) {
            // TODO: mixed
        }

        if ($variableType instanceof MixedVariableType) {
            // TODO: mixed
        } elseif ($variableType instanceof ScalarVariableType) {
            switch ($variableType->type) {
                case ScalarVariableType::TYPE_INTEGER:
                    $schemaBuilder = new IntegerSchemaBuilder();
                    break;
                case ScalarVariableType::TYPE_FLOAT:
                    $schemaBuilder = new FloatSchemaBuilder();
                    break;
                case ScalarVariableType::TYPE_BOOLEAN:
                    $schemaBuilder = new BooleanSchemaBuilder();
                    break;
                case ScalarVariableType::TYPE_STRING:
                    $schemaBuilder = new StringSchemaBuilder();
                    break;
            }
        } elseif ($variableType instanceof ArrayVariableType) {
            if ($variableType->keyType === null) {
                $schemaBuilder = new ArraySchemaBuilder();
            } else {
                $schemaBuilder = new HashmapSchemaBuilder();
            }
            $schemaBuilder = $schemaBuilder->withItemsSchema(
                $this->translate($variableType->itemType, [])
            );
        } elseif ($variableType instanceof ClassVariableType) {
            $schemaBuilder = $this->getClassSchemaBuilder($variableType->class);
        } elseif ($variableType instanceof UnionVariableType) {
            throw new \Exception('Union types are not supported');
        }

        $schemaBuilder = $schemaBuilder->withNullable($variableType->nullable);

        return $schemaBuilder->build();
    }
}
