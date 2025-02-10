<?php

declare(strict_types=1);

/*
 * This file is part of the CleverAge/UiProcessBundle package.
 *
 * Copyright (c) Clever-Age
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CleverAge\UiProcessBundle\Security;

use CleverAge\UiProcessBundle\Repository\UserRepositoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\Pbkdf2PasswordHasher;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class HttpProcessExecutionAuthenticator extends AbstractAuthenticator
{
    public function __construct(private readonly UserRepositoryInterface $userRepository)
    {
    }

    public function supports(Request $request): ?bool
    {
        return 'http_process_execute' === $request->get('_route') && $request->isMethod(Request::METHOD_POST);
    }

    public function authenticate(Request $request): Passport
    {
        if (false === $request->headers->has('Authorization')) {
            throw new AuthenticationException('Missing auth token.');
        }
        $token = $request->headers->get('Authorization');
        $token = str_replace('Bearer ', '', $token ?? '');
        $user = $this->userRepository->findOneBy(
            ['token' => (new Pbkdf2PasswordHasher())->hash($token)]
        );
        if (null === $user) {
            throw new AuthenticationException('Invalid token.');
        }

        return new SelfValidatingPassport(new UserBadge($user->getEmail() ?? ''));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $data = [
            'message' => $exception->getMessage(),
        ];

        return new JsonResponse($data, Response::HTTP_UNAUTHORIZED);
    }
}
