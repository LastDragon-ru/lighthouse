<?php

namespace Tests\Unit\Schema\Directives;

use Tests\TestCase;

class SpreadDirectiveTest extends TestCase
{
    public function testNestedSpread(): void
    {
        $this->mockResolver()
            ->with(null, [
                'foo' => 1,
                'baz' => 2,
            ]);

        $this->schema = /** @lang GraphQL */ '
        type Query {
            foo(input: Foo @spread): Int @mock
        }

        input Foo {
            foo: Int
            bar: Bar @spread
        }

        input Bar {
            baz: Int
        }
        ';

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo(input: {
                foo: 1
                bar: {
                    baz: 2
                }
            })
        }
        ');
    }

    public function testNestedSpreadWithResolver(): void
    {
        $this->mockResolver()
            ->with(null, [
                'foo' => 1,
                'bar__baz' => 2,
            ]);

        $resolver = $this->qualifyTestResolver('spread');
        $this->schema = /** @lang GraphQL */ "
        type Query {
            foo(input: Foo @spread): Int @mock
        }

        input Foo {
            foo: Int
            bar: Bar @spread(resolver: \"{$resolver}\")
        }

        input Bar {
            baz: Int
        }
        ";

        $this->graphQL(/** @lang GraphQL */ '
        {
            foo(input: {
                foo: 1
                bar: {
                    baz: 2
                }
            })
        }
        ');
    }

    public function spread(string $parent, string $current): string {
        return "{$parent}__{$current}";
    }
}
