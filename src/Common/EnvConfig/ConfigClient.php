<?php

declare(strict_types=1);

namespace Temporal\Common\EnvConfig;

use Internal\Toml\Toml;
use Temporal\Common\EnvConfig\Client\ConfigEnv;
use Temporal\Common\EnvConfig\Client\ConfigProfile;
use Temporal\Common\EnvConfig\Client\ConfigToml;
use Temporal\Common\EnvConfig\Exception\ConfigException;
use Temporal\Common\EnvConfig\Exception\DuplicateProfileException;
use Temporal\Common\EnvConfig\Exception\InvalidConfigException;
use Temporal\Common\EnvConfig\Exception\ProfileNotFoundException;

/**
 * Client configuration loaded from TOML and environment variables.
 *
 * This class provides methods to load configuration from TOML files and environment variables,
 * following the Temporal external client configuration specification.
 *
 * Configuration loading hierarchy (later values override earlier ones):
 * 1. Load profile from TOML configuration file
 * 2. Override with environment variables (TEMPORAL_*)
 * 3. SDK defaults are applied for unspecified values
 *
 * Profile names are case-insensitive per specification.
 *
 * @link https://github.com/temporalio/proposals/blob/master/all-sdk/external-client-configuration.md
 *
 * @internal Experimental
 * @psalm-internal Temporal\Common\EnvConfig
 */
final class ConfigClient
{
    /**
     * @param array<non-empty-lowercase-string, ConfigProfile> $profiles Profile configurations keyed by lowercase name
     */
    private function __construct(
        public readonly array $profiles,
    ) {}

    /**
     * Load a single client profile from given sources, applying env overrides.
     *
     * This is the primary method for loading configuration with full control over sources.
     *
     * Loading order (later overrides earlier):
     * 1. Profile from TOML file (if $configFile provided, or TEMPORAL_CONFIG_FILE is set,
     *    or file exists at default platform-specific location)
     * 2. Environment variable overrides (if $env provided)
     *
     * @param non-empty-string|null $profileName Profile name to load. If null, uses TEMPORAL_PROFILE
     *        environment variable or 'default' as fallback.
     * @param non-empty-string|null $configFile Path to TOML config file or TOML content string.
     *        If null, checks TEMPORAL_CONFIG_FILE env var, then checks if file exists at
     *        default platform-specific location.
     * @param array $env Environment variables array for overrides.
     *
     * @throws ProfileNotFoundException If the requested profile is not found
     * @throws InvalidConfigException If configuration file is invalid
     */
    public static function load(
        ?string $profileName = null,
        ?string $configFile = null,
        array $env = [],
    ): ConfigProfile {
        $env = $env ?: \getenv();

        // Load environment config first to get profile name if not specified
        $envConfig = ConfigEnv::fromEnv($env);
        $profileExpected = $profileName !== null || $envConfig->currentProfile !== null;
        $profileName ??= $envConfig->currentProfile ?? 'default';
        $profileNameLower = \strtolower($profileName);

        // Determine config file path: explicit > env var > default location
        $configFile ??= $envConfig->configFile;
        if ($configFile === null) {
            $configFile = self::getDefaultConfigPath($env);
            $configFile === null or \file_exists($configFile) or $configFile = null;
        }

        // Load from file if it exists
        $profile = $configFile === null
            ? null
            : self::loadFromToml($configFile)->profiles[$profileNameLower] ?? null;

        // Merge with environment overrides or use env profile
        if ($profile !== null) {
            $profile = $profile->mergeWith($envConfig->profile);
        } elseif ($envConfig->profile->address !== null || $envConfig->profile->namespace !== null) {
            // Use env profile only if it has meaningful data
            $profile = $envConfig->profile;
        }

        if ($profile !== null) {
            return $profile;
        }

        $profileExpected and throw new ProfileNotFoundException($profileName);

        // Returns empty profile if default doesn't exist and wasn't explicitly requested
        return new ConfigProfile(null, null, null);
    }

    /**
     * Load a single profile directly from environment variables.
     *
     * Uses TEMPORAL_* environment variables to construct configuration.
     *
     * @param array $env Environment variables array.
     */
    public static function loadFromEnv(array $env = []): ConfigProfile
    {
        return ConfigEnv::fromEnv($env ?: \getenv())->profile;
    }

    /**
     * Load all profiles from a TOML configuration file.
     *
     * @param non-empty-string $source File path to TOML config or TOML content string
     *
     * @throws InvalidConfigException If the file cannot be read or TOML is invalid
     */
    public static function loadFromToml(string $source): self
    {
        try {
            // Determine if source is a file path or TOML content
            $toml = \is_file($source)
                ? \file_get_contents($source)
                : $source;

            $toml === false and throw new InvalidConfigException("Failed to read configuration file: {$source}");

            $config = ConfigToml::fromString($toml);
            return new self(self::normalizeProfileNames($config->profiles));
        } catch (ConfigException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new InvalidConfigException(
                "Invalid TOML configuration: {$e->getMessage()}",
                previous: $e,
            );
        }
    }

    /**
     * Serialize the client configuration back to TOML format.
     */
    public function toToml(): string
    {
        return (new ConfigToml(
            profiles: $this->profiles,
        ))->toToml();
    }

    /**
     * Get a profile by name (case-insensitive).
     *
     * @param non-empty-string $name Profile name
     *
     * @throws ProfileNotFoundException If profile does not exist
     */
    public function getProfile(string $name): ConfigProfile
    {
        $lower = \strtolower($name);
        isset($this->profiles[$lower]) or throw new ProfileNotFoundException($name);

        return $this->profiles[$lower];
    }

    /**
     * Check if a profile exists (case-insensitive).
     *
     * @param non-empty-string $name Profile name
     */
    public function hasProfile(string $name): bool
    {
        return isset($this->profiles[\strtolower($name)]);
    }

    /**
     * Normalize profile names to lowercase and validate for duplicates.
     *
     * @param array<non-empty-string, ConfigProfile> $profiles Profiles with original case names
     * @return array<non-empty-lowercase-string, ConfigProfile> Profiles with lowercase keys
     * @throws DuplicateProfileException If duplicate names found
     */
    private static function normalizeProfileNames(array $profiles): array
    {
        $normalized = [];
        foreach ($profiles as $name => $profile) {
            $lower = \strtolower($name);
            isset($normalized[$lower]) and throw new DuplicateProfileException($name, $lower);
            $normalized[$lower] = $profile;
        }
        return $normalized;
    }

    /**
     * Get the default configuration file path based on the operating system.
     *
     * Returns the platform-specific path to temporal.toml configuration file:
     * - Linux/Unix: $XDG_CONFIG_HOME/temporalio/temporal.toml (default: ~/.config/temporalio/temporal.toml)
     * - macOS: ~/Library/Application Support/temporalio/temporal.toml
     * - Windows: %APPDATA%/temporalio/temporal.toml
     *
     * Note: This method returns the expected path regardless of whether the file exists.
     * The caller is responsible for checking file existence.
     *
     * @param array $env Environment variables array
     * @return non-empty-string|null Path to default config file, or null if home directory cannot be determined
     */
    private static function getDefaultConfigPath(array $env): ?string
    {
        // Check APPDATA for Windows first
        $configDir = $env['APPDATA'] ?? null;

        if ($configDir === null) {
            $home = $env['HOME'] ?? $env['USERPROFILE'] ?? null;
            if ($home === null) {
                return null;
            }

            $configDir = match (\PHP_OS_FAMILY) {
                'Windows' => $home . '\\AppData\\Roaming',
                'Darwin' => $home . '/Library/Application Support',
                default => $env['XDG_CONFIG_HOME'] ?? ($home . '/.config'),
            };
        }

        return $configDir . \DIRECTORY_SEPARATOR . 'temporalio' . \DIRECTORY_SEPARATOR . 'temporal.toml';
    }
}
