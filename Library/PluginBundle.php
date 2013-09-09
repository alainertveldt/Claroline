<?php

namespace Claroline\CoreBundle\Library;

use Claroline\InstallationBundle\Bundle\InstallableBundle;
use Claroline\KernelBundle\Bundle\AutoConfigurableInterface;
use Claroline\KernelBundle\Bundle\ConfigurationBuilder;

/**
 * Base class of all the plugin bundles on the claroline platform.
 */
abstract class PluginBundle extends InstallableBundle implements AutoConfigurableInterface
{
    public function supports($environment)
    {
        return true;
    }

    public function getConfiguration($environment)
    {
        return new ConfigurationBuilder();
    }

    final public function getVendorName()
    {
        $namespaceParts = explode('\\', $this->getNamespace());

        return $namespaceParts[0];
    }

    final public function getBundleName()
    {
        $namespaceParts = explode('\\', $this->getNamespace());

        return $namespaceParts[1];
    }

    public function getRoutingResourcesPaths()
    {
        $ds = DIRECTORY_SEPARATOR;
        $path = $this->getPath() . $ds . 'Resources' . $ds . 'config' . $ds . 'routing.yml';

        if (file_exists($path)) {
            return array($path);
        }

        return array();
    }

    public function getRoutingPrefix()
    {
        $vendor = $this->getVendorName();
        $prefix = $this->getBundleName();
        $pattern = '#^(.+)Bundle$#';

        if (preg_match($pattern, $prefix, $matches)) {
            $prefix = $matches[1];
        }

        $prefix = strtolower("{$vendor}_{$prefix}");

        return $prefix;
    }

    public function getConfigFile()
    {
        $ds = DIRECTORY_SEPARATOR;
        $defaultFilePath = $this->getPath() . $ds . 'Resources' . $ds . 'config' . $ds . 'config.yml';

        if (file_exists($defaultFilePath)) {
            return $defaultFilePath;
        }

        return null;
    }

    public function getImgFolder()
    {
        $ds = DIRECTORY_SEPARATOR;
        $path = "{$this->getPath()}{$ds}Resources{$ds}public{$ds}images{$ds}icons";

        if (is_dir($path)) {
            return $path;
        }

        return null;
    }

    public function getAssetsFolder()
    {
        return strtolower(str_replace('Bundle', '', $this->getVendorName().$this->getBundleName()));
    }
}
