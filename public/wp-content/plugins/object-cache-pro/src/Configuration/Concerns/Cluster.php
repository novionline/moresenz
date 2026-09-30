<?php
/**
 * Copyright © 2019-2026 Rhubarb Tech Inc. All Rights Reserved.
 *
 * The Object Cache Pro Software and its related materials are property and confidential
 * information of Rhubarb Tech Inc. Any reproduction, use, distribution, or exploitation
 * of the Object Cache Pro Software and its related materials, in whole or in part,
 * is strictly forbidden unless prior permission is obtained from Rhubarb Tech Inc.
 *
 * In addition, any reproduction, use, distribution, or exploitation of the Object Cache Pro
 * Software and its related materials, in whole or in part, is subject to the End-User License
 * Agreement accessible in the included `LICENSE` file, or at: https://objectcache.pro/eula
 */

declare(strict_types=1);

namespace RedisCachePro\Configuration\Concerns;

use RedisCachePro\Exceptions\ConfigurationException;
use RedisCachePro\Exceptions\ConfigurationInvalidException;

trait Cluster
{
    /**
     * The cluster configuration name as string, or an array of cluster nodes.
     *
     * @var string|array<string>|null
     */
    protected $cluster;

    /**
     * The cluster failover strategy.
     *
     * @var string
     */
    protected $cluster_failover = 'error';

    /**
     * The cluster distribution strategy (Requires Relay v0.22.0+).
     *
     * @var ?string
     */
    protected $cluster_distribution_strategy;

    /**
     * The cluster failover strategy (Requires Relay v0.22.0+).
     *
     * @var ?string
     */
    protected $cluster_failover_strategy;

    /**
     * The cluster availability zone (Requires Relay v0.22.0+).
     *
     * @var ?string
     */
    protected $cluster_az;

    /**
     * The available cluster failover strategies.
     *
     * @return array<string>
     */
    protected function clusterFailovers()
    {
        return [
            // Only send commands to primary nodes
            'none',

            // If a primary can't be reached, and it has replicas, failover for read commands
            'error',

            // Always distribute readonly commands between primaries and replicas, at random
            'distribute',

            // Always distribute readonly commands to the replicas, at random
            'distribute_replicas',
        ];
    }

    /**
     * The available cluster distribution strategies (Requires Relay v0.22.0+).
     *
     * @return array<string>
     */
    protected function clusterDistributionStrategies()
    {
        return [
            // Send readonly commands to the primary node only
            'none',

            // Distribute randomly between the primary and its replicas, stop on first failed attempt
            'random',

            // Distribute randomly among replicas only, stop on first failed attempt
            'random_replica',

            // Distribute randomly among replicas only, iterate through all until working
            'replicas',

            // Distribute between the primary and its replicas, iterate through all until working
            'all',
        ];
    }

    /**
     * The available cluster failover/retry strategies (Requires Relay v0.22.0+).
     *
     * @return array<string>
     */
    protected function clusterFailoverStrategies()
    {
        return [
            // Don't retry
            'none',

            // Retry the readonly command on a randomly selected replica
            'random_replica',

            // Retry the readonly command on the primary node (only if failed node is a replica)
            'primary',

            // Retry the readonly command on all replicas, excluding the failed node
            'replicas',

            // Retry the readonly command on all other nodes, excluding the failed node
            'all',
        ];
    }

    /**
     * Set the cluster configuration name or an array of cluster nodes.
     *
     * @param  string|array<string>  $cluster
     * @return void
     */
    public function setCluster(
        #[\SensitiveParameter]
        $cluster
    ) {
        if (is_null($cluster)) {
            return;
        }

        if (! \is_string($cluster) && ! \is_array($cluster)) {
            throw new ConfigurationException(
                '`cluster` must be a configuration name (string) or an array of cluster nodes'
            );
        }

        if (empty($cluster)) {
            throw new ConfigurationInvalidException('`cluster` must be a non-empty string or array');
        }

        $this->cluster = $cluster;
    }

    /**
     * Set the automatic replica failover / distribution.
     *
     * @param  string  $failover
     * @return void
     */
    public function setClusterFailover($failover)
    {
        $failover = \strtolower((string) $failover);
        $failover = \str_replace('distribute_slaves', 'distribute_replicas', $failover);

        if (! \in_array($failover, $this->clusterFailovers())) {
            throw new ConfigurationException("Cluster failover `{$failover}` is not supported");
        }

        $this->cluster_failover = $failover;
    }

    /**
     * Legacy method to set the automatic replica failover / distribution.
     *
     * @param  string  $failover
     * @return void
     */
    public function setSlaveFailover($failover)
    {
        $this->setClusterFailover($failover);
    }

    /**
     * Returns the value of the `RedisCluster::FAILOVER_*` constant.
     *
     * @return  int
     */
    public function getClusterFailover()
    {
        $failover = \str_replace('distribute_replicas', 'distribute_slaves', $this->cluster_failover);
        $failover = \strtoupper($failover);

        return \constant("\RedisCluster::FAILOVER_{$failover}");
    }

    /**
     * Set the cluster distribution strategy (Requires Relay v0.22.0+).
     *
     * @param  ?string  $strategy
     * @return void
     */
    public function setClusterDistributionStrategy($strategy)
    {
        if (\is_null($strategy)) {
            $this->cluster_distribution_strategy = null;

            return;
        }

        $strategy = \strtolower((string) $strategy);

        if (! \in_array($strategy, $this->clusterDistributionStrategies())) {
            throw new ConfigurationException("Cluster distribution strategy `{$strategy}` is not supported");
        }

        $this->cluster_distribution_strategy = $strategy;
    }

    /**
     * Returns the value of the `Relay\Cluster::DISTRIBUTE_*` constant, or `null` when unset.
     *
     * @return ?int
     */
    public function getClusterDistributionStrategy()
    {
        if (\is_null($this->cluster_distribution_strategy)) {
            return null;
        }

        return \constant('\Relay\Cluster::DISTRIBUTE_' . \strtoupper($this->cluster_distribution_strategy));
    }

    /**
     * Set the cluster failover strategy (Requires Relay v0.22.0+).
     *
     * @param  ?string  $strategy
     * @return void
     */
    public function setClusterFailoverStrategy($strategy)
    {
        if (\is_null($strategy)) {
            $this->cluster_failover_strategy = null;

            return;
        }

        $strategy = \strtolower((string) $strategy);

        if (! \in_array($strategy, $this->clusterFailoverStrategies())) {
            throw new ConfigurationException("Cluster failover strategy `{$strategy}` is not supported");
        }

        $this->cluster_failover_strategy = $strategy;
    }

    /**
     * Returns the value of the `Relay\Cluster::FAILOVER_*` constant, or `null` when unset.
     *
     * @return ?int
     */
    public function getClusterFailoverStrategy()
    {
        if (\is_null($this->cluster_failover_strategy)) {
            return null;
        }

        return \constant('\Relay\Cluster::FAILOVER_' . \strtoupper($this->cluster_failover_strategy));
    }

    /**
     * Set the cluster availability zone (Requires Relay v0.22.0+).
     *
     * @param  ?string  $zone
     * @return void
     */
    public function setClusterAz($zone)
    {
        if (\is_null($zone)) {
            $this->cluster_az = null;

            return;
        }

        if (! \is_string($zone) || $zone === '') {
            throw new ConfigurationInvalidException('`cluster_az` must be a non-empty string');
        }

        $this->cluster_az = $zone;
    }
}
