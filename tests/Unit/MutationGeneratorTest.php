<?php

namespace MrNamra\AutoGraphQL\Tests\Unit;

use MrNamra\AutoGraphQL\Tests\TestCase;
use MrNamra\AutoGraphQL\MutationGenerator;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

class MutationGeneratorTest extends TestCase
{
    /** @test */
    public function it_captures_method_and_route_in_resolver()
    {
        $route = [
            'method' => 'POST',
            'controller' => 'App\Http\Controllers\AuthController',
            'action' => 'login',
            'model' => 'App\Models\User',
            'attribute' => (object)['mutation' => 'login', 'description' => 'Login'],
        ];

        $type = new ObjectType(['name' => 'User', 'fields' => ['id' => ['type' => Type::id()]]]);
        $inputFields = ['email' => 'String!', 'password' => 'String!'];

        $generator = new MutationGenerator();
        $mutations = $generator->generate($route, $type, $inputFields);

        $this->assertArrayHasKey('login', $mutations);
        $resolver = $mutations['login']['resolve'];

        $this->assertIsCallable($resolver);
        
        // Check closure reflection to see captured variables.
        $reflection = new \ReflectionFunction($resolver);
        $staticVariables = $reflection->getStaticVariables();
        
        $this->assertArrayHasKey('method', $staticVariables);
        $this->assertArrayHasKey('route', $staticVariables);
        $this->assertEquals('POST', $staticVariables['method']);
    }
}
