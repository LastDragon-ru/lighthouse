<?php

namespace Tests\Unit\Execution\Arguments;

use GraphQL\Language\Parser;
use Nuwave\Lighthouse\Execution\Arguments\Argument;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;
use Nuwave\Lighthouse\Schema\Directives\RenameDirective;
use Nuwave\Lighthouse\Schema\Directives\SpreadDirective;
use Tests\TestCase;

class ArgumentSetTest extends TestCase
{
    public function testHasArgument(): void
    {
        $set = new ArgumentSet();

        $this->assertFalse($set->has('foo'));

        $set->arguments['foo'] = new Argument();
        $this->assertFalse($set->has('foo'));

        $arg = new Argument();
        $arg->value = null;
        $set->arguments['foo'] = $arg;
        $this->assertFalse($set->has('foo'));

        $arg->value = false;
        $this->assertTrue($set->has('foo'));

        $arg->value = 'foobar';
        $this->assertTrue($set->has('foo'));
    }

    public function testSpread(): void
    {
        $directiveCollection = collect([$this->makeSpreadDirective()]);
        $directiveResolverCollection = collect([$this->makeSpreadDirective(
            $this->qualifyTestResolver('spread')
        )]);

        // Those are the leave values we want in the spread result
        $foo = new Argument();
        $foo->value = 1;

        $baz = new Argument();
        $baz->value = 2;

        $barInput = new ArgumentSet();
        $barInput->arguments['baz'] = $baz;

        $barArgument = new Argument();
        $barArgument->directives = $directiveCollection;
        $barArgument->value = $barInput;

        $quxArgument = new Argument();
        $quxArgument->directives = $directiveResolverCollection;
        $quxArgument->value = $barInput;

        $quuzArgument = new Argument();
        $quuzArgument->directives = $directiveResolverCollection;
        $quuzArgument->value = new ArgumentSet();
        $quuzArgument->value->arguments['qux'] = $quxArgument;

        $fooInput = new ArgumentSet();
        $fooInput->arguments['foo'] = $foo;
        $fooInput->arguments['bar'] = $barArgument;
        $fooInput->arguments['qux'] = $quxArgument;
        $fooInput->arguments['quuz'] = $quuzArgument;

        $inputArgument = new Argument();
        $inputArgument->directives = $directiveCollection;
        $inputArgument->value = $fooInput;

        $quuxArgument = new Argument();
        $quuxArgument->value = [$fooInput, $barInput];

        $argumentSet = new ArgumentSet();
        $argumentSet->directives = $directiveCollection;
        $argumentSet->arguments['input'] = $inputArgument;
        $argumentSet->arguments['quux'] = $quuxArgument;

        $spreadArgumentSet = $argumentSet->spread();
        $spreadArguments = $spreadArgumentSet->arguments;

        $this->assertSame([
            'foo' => $foo,
            'baz' => $baz,
            'qux__baz' => $baz,
            'quuz__qux__baz' => $baz,
            'quux' => $quuxArgument,
        ], $spreadArguments);

        $this->assertSame([
            'foo' => $foo,
            'baz' => $baz,
            'qux__baz' => $baz,
            'quuz__qux__baz' => $baz,
        ], $spreadArguments['quux']->value[0]->arguments);

        $this->assertSame([
            'baz' => $baz,
        ], $spreadArguments['quux']->value[1]->arguments);
    }

    public function testSingleFieldToArray(): void
    {
        $foo = new Argument();
        $fooValue = 1;
        $foo->value = $fooValue;

        $argumentSet = new ArgumentSet();
        $argumentSet->arguments['foo'] = $foo;

        $this->assertSame(
            [
                'foo' => $fooValue,
            ],
            $argumentSet->toArray()
        );
    }

    public function testInputObjectToArray(): void
    {
        $foo = new Argument();
        $fooValue = 1;
        $foo->value = $fooValue;

        $fooInput = new ArgumentSet();
        $fooInput->arguments['foo'] = $foo;

        $inputArgument = new Argument();
        $inputArgument->value = $fooInput;

        $argumentSet = new ArgumentSet();
        $argumentSet->arguments['input'] = $inputArgument;

        $this->assertSame(
            [
                'input' => [
                    'foo' => $fooValue,
                ],
            ],
            $argumentSet->toArray()
        );
    }

    public function testListOfInputObjectsToArray(): void
    {
        $foo = new Argument();
        $fooValue = 1;
        $foo->value = $fooValue;

        $fooInput = new ArgumentSet();
        $fooInput->arguments['foo'] = $foo;

        $inputArgument = new Argument();
        $inputArgument->value = [$fooInput, $fooInput];

        $argumentSet = new ArgumentSet();
        $argumentSet->arguments['input'] = $inputArgument;

        $this->assertSame(
            [
                'input' => [
                    [
                        'foo' => $fooValue,
                    ],
                    [
                        'foo' => $fooValue,
                    ],
                ],
            ],
            $argumentSet->toArray()
        );
    }

    public function testAddValueAtRootLevel(): void
    {
        $set = new ArgumentSet();
        $set->addValue('foo', 42);

        $this->assertSame(42, $set->arguments['foo']->value);
    }

    public function testAddValueDeep(): void
    {
        $set = new ArgumentSet();
        $set->addValue('foo.bar', 42);

        $foo = $set->arguments['foo']->value;

        $this->assertSame(42, $foo->arguments['bar']->value);
    }

    public function testRenameInput(): void
    {
        $firstName = new Argument();
        $firstName->value = 'Michael';
        $firstName->directives = collect([$this->makeRenameDirective('first_name')]);

        $argumentSet = new ArgumentSet();
        $argumentSet->arguments = [
            'firstName' => $firstName,
        ];

        $renamedSet = $argumentSet->rename();

        $this->assertSame(
            [
                'first_name' => $firstName,
            ],
            $renamedSet->arguments
        );
    }

    public function testRenameNested(): void
    {
        $secondLevelArg = new Argument();
        $secondLevelArg->value = 'Michael';
        $secondLevelArg->directives = collect([$this->makeRenameDirective('second_internal')]);

        $secondLevelSet = new ArgumentSet();
        $secondLevelSet->arguments = [
            'secondExternal' => $secondLevelArg,
        ];

        $firstLevelArg = new Argument();
        $firstLevelArg->value = $secondLevelSet;
        $firstLevelArg->directives = collect([$this->makeRenameDirective('first_internal')]);

        $firstLevelSet = new ArgumentSet();
        $firstLevelSet->arguments = [
            'firstExternal' => $firstLevelArg,
        ];

        $renamedFirstLevel = $firstLevelSet->rename();

        $renamedSecondLevel = $renamedFirstLevel->arguments['first_internal']->value;
        $this->assertSame(
            [
                'second_internal' => $secondLevelArg,
            ],
            $renamedSecondLevel->arguments
        );
    }

    protected function makeRenameDirective(string $attribute): RenameDirective
    {
        $renameDirective = new RenameDirective();
        $renameDirective->hydrate(
            Parser::constDirective(/** @lang GraphQL */ "@rename(attribute: \"$attribute\")"),
            // We require some placeholder for the directive definition to sit on
            Parser::fieldDefinition(/** @lang GraphQL */ 'placeholder: ID')
        );

        return $renameDirective;
    }

    protected function makeSpreadDirective(string $resolver = null): SpreadDirective
    {
        $directiveNode = $resolver
            ? Parser::constDirective(/** @lang GraphQL */ "@spread(resolver: \"$resolver\")")
            : Parser::constDirective(/** @lang GraphQL */ '@spread');
        $definitionNode = Parser::fieldDefinition(/** @lang GraphQL */ 'placeholder: ID');
        $spreadDirective = (new SpreadDirective())->hydrate($directiveNode, $definitionNode);

        return $spreadDirective;
    }

    public function spread(string $parent, string $current): string
    {
        return "{$parent}__{$current}";
    }
}
