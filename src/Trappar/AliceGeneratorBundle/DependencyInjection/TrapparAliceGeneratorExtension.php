<?php

namespace Trappar\AliceGeneratorBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;
use Trappar\AliceGenerator\Exception\RuntimeException;

class TrapparAliceGeneratorExtension extends ConfigurableExtension
{
    /**
     * Configures the passed container according to the merged configuration.
     *
     * @param array            $config
     * @param ContainerBuilder $container
     */
    public function loadInternal(array $config, ContainerBuilder $container)
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yml');

        $bundles = $container->getParameter('kernel.bundles');

        $directories = [];
        if ($config['metadata']['auto_detection']) {
            foreach ($bundles as $name => $class) {
                $ref                                   = new \ReflectionClass($class);
                $directories[$ref->getNamespaceName()] = dirname($ref->getFileName()) . '/Resources/config/fixture';
            }
        }

        foreach ($config['metadata']['directories'] as $directory) {
            $directory['path'] = rtrim(str_replace('\\', '/', $directory['path']), '/');
            if ('@' === $directory['path'][0]) {
                preg_match('~^@([a-z0-9_]+)~i', $directory['path'], $match);

                if (isset($match[1])) {
                    list($prefixedBundleName, $bundleName) = $match;
                    if (!isset($bundles[$bundleName])) {
                        throw new RuntimeException(sprintf('The bundle "%s" has not been registered with AppKernel. Available bundles: %s', $bundleName, implode(', ', array_keys($bundles))));
                    }
                    $ref = new \ReflectionClass($bundles[$bundleName]);
                    $directory['path'] = dirname($ref->getFileName()).substr($directory['path'], strlen($prefixedBundleName));
                }
            }
            $directories[rtrim($directory['namespace_prefix'], '\\')] = rtrim($directory['path'], '\\/');
        }

        $container
            ->getDefinition('trappar_alice_generator.metadata.file_locator')
            ->addArgument($directories);

        $container
            ->getDefinition('trappar_alice_generator.yaml_writer')
            ->addArgument($config['yaml']['inline'])
            ->addArgument($config['yaml']['indent']);

        $container
            ->getDefinition('trappar_alice_generator.value_visitor')
            ->addArgument($config['strictTypeChecking']);
    }

    public function getConfiguration(array $config, ContainerBuilder $container)
    {
        return new Configuration();
    }
}
