<?php
declare(strict_types=1);

use Tests\TestCase;
use ReallySimpleJWT\Token;
use App\Infrastructure\Database\SQL\UserRepository;
use Slim\Middleware\ErrorMiddleware;
use App\Application\Actions\ActionError;
use App\Application\Actions\ActionPayload;
use App\Application\Handlers\HttpErrorHandler;
use App\Presentation\Errors\User\UserNotFoundException;

class SessionCreateActionTest extends TestCase
{
    public function testAction()
    {
        $app = $this->getAppInstance();

        /** @var Container $container */
        $container = $app->getContainer();

        $user = ['id' => 1, 'username' => 'user1', 'password' => '123456'];

        $hashedPassword = $this->hashPassword($user['password']);

        $userRepositoryProphecy = $this->prophesize(UserRepository::class);
        $userRepositoryProphecy
            ->findUserOfUsername($user['username'])
            ->willReturn([
                'id' => 1,
                'password' => $hashedPassword
            ])
            ->shouldBeCalledOnce();

        $container->set(UserRepository::class, $userRepositoryProphecy->reveal());
        $request = $this->createRequest('POST', '/sessions');
        $request->getBody()->write(json_encode($user));
        $response = $app->handle($request);

        $payload = (string) $response->getBody();
        $parsedPayload = json_decode($payload, true);
        $userId = Token::getPayload($parsedPayload['data']['token'], $_ENV['SECRET'])['user_id'];

        $this->assertEquals($user['id'], $userId);
    }

    public function testInvalidPasswordException()
    {
        $app = $this->getAppInstance();

        $callableResolver = $app->getCallableResolver();
        $responseFactory = $app->getResponseFactory();

        $errorHandler = new HttpErrorHandler($callableResolver, $responseFactory);
        $errorMiddleware = new ErrorMiddleware($callableResolver, $responseFactory, true, false, false);
        $errorMiddleware->setDefaultErrorHandler($errorHandler);

        $app->add($errorMiddleware);

        /** @var Container $container */
        $container = $app->getContainer();

        $user = ['username' => 'user1', 'password' => 'asdfasdfasdfag34'];

        $wrongPassword = '389457349587345';
        $hashedPassword = $this->hashPassword($wrongPassword);

        $userRepositoryProphecy = $this->prophesize(UserRepository::class);
        $userRepositoryProphecy
            ->findUserOfUsername($user['username'])
            ->willReturn([
                'id' => 1,
                'password' => $hashedPassword
            ])
            ->shouldBeCalledOnce();

        $container->set(UserRepository::class, $userRepositoryProphecy->reveal());

        $request = $this->createRequest('POST', '/sessions');
        $request->getBody()->write(json_encode($user));
        $response = $app->handle($request);

        $payload = (string) $response->getBody();
        $expectedError = new ActionError(ActionError::RESOURCE_NOT_FOUND, 'Wrong password, try again.');
        $expectedPayload = new ActionPayload(404, null, $expectedError);
        $serializedPayload = json_encode($expectedPayload, JSON_PRETTY_PRINT);

        $this->assertEquals($serializedPayload, $payload);
    }

    public function testInvalidUserIdException()
    {
        $app = $this->getAppInstance();

        $callableResolver = $app->getCallableResolver();
        $responseFactory = $app->getResponseFactory();

        $errorHandler = new HttpErrorHandler($callableResolver, $responseFactory);
        $errorMiddleware = new ErrorMiddleware($callableResolver, $responseFactory, true, false, false);
        $errorMiddleware->setDefaultErrorHandler($errorHandler);

        $app->add($errorMiddleware);

        /** @var Container $container */
        $container = $app->getContainer();

        $user = ['username' => 'user1', 'password' => '123456'];

        $userRepositoryProphecy = $this->prophesize(UserRepository::class);
        $userRepositoryProphecy
            ->findUserOfUsername($user['username'])
            ->willThrow(new UserNotFoundException())
            ->shouldBeCalledOnce();

        $container->set(UserRepository::class, $userRepositoryProphecy->reveal());

        $request = $this->createRequest('POST', '/sessions');
        $request->getBody()->write(json_encode($user));
        $response = $app->handle($request);

        $payload = (string) $response->getBody();
        $expectedError = new ActionError(ActionError::RESOURCE_NOT_FOUND, 'User not found.');
        $expectedPayload = new ActionPayload(404, null, $expectedError);
        $serializedPayload = json_encode($expectedPayload, JSON_PRETTY_PRINT);

        $this->assertEquals($serializedPayload, $payload);
    }

    private function hashPassword(string $password)
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }
}
