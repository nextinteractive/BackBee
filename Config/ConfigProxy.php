<?php

/*
 * Copyright (c) 2011-2015 Lp digital system
 *
 * This file is part of BackBee.
 *
 * BackBee is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * BackBee is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with BackBee. If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Charles Rouillon <charles.rouillon@lp-digital.fr>
 */

namespace BackBee\Config;

use BackBee\DependencyInjection\ContainerInterface;
use BackBee\DependencyInjection\Dumper\DumpableServiceProxyInterface;

/**
 * This interface must be implemented if you want to use a proxy class instead of your service real class.
 *
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      e.chau <eric.chau@lp-digital.fr>
 */
class ConfigProxy extends Config implements DumpableServiceProxyInterface
{
    /**
     * ConfigProxy's constructor.
     *
     * @param array $dump
     */
    public function __construct()
    {
        $this->is_restored = false;
    }

    /**
     * Restore current service to the dump's state.
     *
     * @param array $dump the dump provided by DumpableServiceInterface::dump() from where we can
     *                    restore current service
     */
    public function restore(ContainerInterface $container, array $dump)
    {
        $this->basedir = $dump['basedir'];
        $this->raw_parameters = $dump['raw_parameters'];
        $this->environment = $dump['environment'];
        $this->debug = $dump['debug'];
        $this->yml_names_to_ignore = $dump['yml_names_to_ignore'];

        if (true === $dump['has_container']) {
            $this->setContainer($container);
        }

        if (true === $dump['has_cache']) {
            $this->setCache($container->get('cache.bootstrap'));
        }

        $this->is_restored = true;
    }
}
