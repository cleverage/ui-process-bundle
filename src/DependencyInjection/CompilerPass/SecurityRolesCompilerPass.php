<?php

/*
 * This file is part of the CleverAge/UiProcessBundle package.
 *
 * Copyright (c) Clever-Age
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CleverAge\UiProcessBundle\DependencyInjection\CompilerPass;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class SecurityRolesCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if ($container->hasDefinition('security.role_hierarchy')) {
            // For each configured process, add ROLE_PROCESS_VIEW#<code> and ROLE_PROCESS_EXECUTE#<code> under ROLE_SUPER_ADMIN role
            $pbExtCfg = $container->getExtensionConfig('clever_age_process');
            $processCodes = array_keys(array_merge(...array_column($pbExtCfg, 'configurations')));
            $processRoles = array_merge(...array_map(fn ($code) => ['ROLE_PROCESS_VIEW#'.$code, 'ROLE_PROCESS_EXECUTE#'.$code], $processCodes));
            $roleHierarchy = $container->getParameter('security.role_hierarchy.roles');
            if (\is_array($roleHierarchy)) {
                $roleHierarchy['ROLE_SUPER_ADMIN'] = array_merge($roleHierarchy['ROLE_SUPER_ADMIN'] ?? [], $processRoles);
                $container->setParameter('security.role_hierarchy.roles', $roleHierarchy);
                $container->getDefinition('security.role_hierarchy')->replaceArgument(0, $roleHierarchy);
            }
        }
    }
}
