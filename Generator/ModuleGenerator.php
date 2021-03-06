<?php

/**
 * This file is part of the Clastic package.
 *
 * (c) Dries De Peuter <dries@nousefreak.be>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Clastic\GeneratorBundle\Generator;

use Sensio\Bundle\GeneratorBundle\Generator\Generator;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

/**
 * Generates a Module inside a bundle.
 *
 * @author Dries De Peuter <dries@nousefreak.be>
 */
class ModuleGenerator extends Generator
{
    /**
     * @param BundleInterface $bundle
     * @param string          $module
     * @param string          $entity
     */
    public function generate(BundleInterface $bundle, $module, $entity)
    {
        $parameters = array(
            'namespace' => $bundle->getNamespace(),
            'bundle' => $bundle->getName(),
            'module' => $module,
            'entity' => $entity,
            'identifier' => Container::underscore($module),
            'bundle_alias' => Container::underscore($bundle->getName()),
        );

        $dir = $bundle->getPath();

        $moduleFile = $dir.'/Module/'.$module.'Module.php';
        if (file_exists($moduleFile)) {
            throw new \RuntimeException(sprintf('Module "%s" already exists', $module));
        }

        $formExtensionFile = $dir.'/Form/Module/'.$module.'FormExtension.php';
        if (file_exists($formExtensionFile)) {
            throw new \RuntimeException(sprintf('FormExtension "%s" already exists', $module));
        }

        $this->renderFile('module/Module.php.twig', $moduleFile, $parameters);
        $this->renderFile('module/FormExtension.php.twig', $formExtensionFile, $parameters);
        $this->updateDependencyInjection($bundle, $parameters);
    }

    private function updateDependencyInjection(BundleInterface $bundle, array $parameters)
    {
        $file = $bundle->getPath().'/Resources/config/services.';

        switch (true) {
            case file_exists($file.'xml'):
                $this->updateDependencyInjectionXml($bundle, $parameters);
                break;
            default;
                throw new \RuntimeException(sprintf('Dependency injection type not found. %s{%s}', $file, implode(',', array('xml'))));
        }
    }

    /**
     * <parameters>
     *  <parameter key="app.project.module.class">AppBundle\Module\ProjectModule</parameter>
     * </parameters>
     * <service id="app.project.module" class="%app.project.module.class%">
     *  <tag name="clastic.module" node_module="true" alias="project" />
     *  <tag name="clastic.node_form" build_service="app.project.module.form_build" />
     * </service>.
     *
     * @param BundleInterface $bundle
     * @param array           $data
     */
    private function updateDependencyInjectionXml(BundleInterface $bundle, array $data)
    {
        $file = $bundle->getPath().'/Resources/config/services.xml';
        $xml = simplexml_load_file($file);

        $parameters = $xml->parameters ? $xml->parameters : $xml->addChild('parameters');
        $services = $xml->services ? $xml->services : $xml->addChild('services');

        $moduleServiceName = sprintf('%s.%s.module', $data['bundle_alias'], $data['identifier']);

        $moduleParameter = $parameters->addChild('parameter', sprintf('%s\Module\%sModule', $data['namespace'], $data['module']));
        $moduleParameter->addAttribute('key', sprintf('%s.class', $moduleServiceName));

        $formExtensionParameter = $parameters->addChild('parameter', sprintf('%s\Form\Module\%sFormExtension', $data['namespace'], $data['module']));
        $formExtensionParameter->addAttribute('key', sprintf('%s.form_extension.class', $moduleServiceName));

        $moduleService = $services->addChild('service');
        $moduleService->addAttribute('id', sprintf('%s.%s.module', $data['bundle_alias'], $data['identifier']));
        $moduleService->addAttribute('class', sprintf('%%%s.class%%', $moduleServiceName));

        $moduleTag = $moduleService->addChild('tag');
        $moduleTag->addAttribute('name', 'clastic.module');
        $moduleTag->addAttribute('node_module', 'true');
        $moduleTag->addAttribute('alias', $data['identifier']);

        $formExtensionTag = $moduleService->addChild('tag');
        $formExtensionTag->addAttribute('name', 'clastic.node_form');
        $formExtensionTag->addAttribute('build_service', sprintf('%s.form_extension', $moduleServiceName));

        $formExtensionService = $services->addChild('service');
        $formExtensionService->addAttribute('id', sprintf('%s.form_extension', $moduleServiceName));
        $formExtensionService->addAttribute('class', sprintf('%%%s.form_extension.class%%', $moduleServiceName));

        $xml->saveXML($file);
    }
}
