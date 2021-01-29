<?php

declare(strict_types=1);

namespace Aquis\XporterBundle\Command;

use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Aquis\XporterBundle\Service\FixtureDump\EntityReader;
use Aquis\XporterBundle\Service\FixtureDump\FixturesWriter;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use TeamLab\Bundle\FixturesBundle\Exception\CommandException;

/**
 * Class DoctrineFixtureDump.
 *
 * @copyright Aquis Grana impex srl (http://www.webnou.ro/)
 * @author    Petronel Malutan <malutanpetronel@gmail.com>
 */
class DoctrineFixtureDump extends Command implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    /**
     * we keep the id's for some entities - mostly for the imported ones.
     * mostly works in oracle.
     *
     * @var array
     */
    private $keepIdFor = [
        //'Sylius\Component\Core\Model\AdminUser',
    ];

    private $alwaysXport = [
        'App\\User\\LoginBundle\\Entity\\User',
    ];

    /**
     * @var EntityReader
     */
    private $entityReader;

    /**
     * @var FixturesWriter
     */
    private $fixturesWriter;

    /**
     * @var bool
     */
    private $debug;

    /**
     * @var array
     */
    protected $processedObjects = [];

    /**
     * @var array
     */
    private $embedObjectsToProcess = [];

    /**
     * DoctrineFixtureDump constructor.
     */
    public function __construct(EntityReader $entityReader, FixturesWriter $fixturesWriter)
    {
        parent::__construct();
        $this->entityReader = $entityReader;
        $this->fixturesWriter = $fixturesWriter;
    }

    /**
     * Configuration for command.
     */
    protected function configure()
    {
        $this
            ->setName('aquis:fixtures:dump')
            ->setDescription('Dump your database data to fixtures.')
            ->addArgument(
                'entity',
                InputArgument::REQUIRED,
                'Entity you want to export (App\\Entity\\Product\\Product)'
            )
            ->addOption(
                'ids',
                'i',
                InputOption::VALUE_REQUIRED,
                'Ids to be extracted', '1,3'
            )
            ->addOption(
                'debug',
                'd',
                InputOption::VALUE_OPTIONAL,
                'Debug mode, more verbose output', 0
            )
        ;
    }

    /**
     * @return int|void|null
     *
     * @throws \Doctrine\ORM\ORMException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $targetEntity = $input->getArgument('entity');
        $targetIds = $input->getOption('ids');
        $this->debug = $input->getOption('debug');

        $entities = $this->entityReader->getAllEntities();

        $targetIds = explode(',', $targetIds);

        $this->fixturesWriter->setFixturesDumpHeader($targetEntity, $targetIds);

        foreach ($targetIds as $targetId) {
            foreach ($entities as $entityName) {
                if ($targetEntity !== $entityName) {
                    continue;
                }

                // we've found entity we want to process so we set reader to be aware to that entity
                $this->entityReader->setWorkingClass($entityName);

                $output->writeln(sprintf('Dumping data starting from entity "<info>%s</info>" with id "<info>%s</info>"', $entityName, $targetId));

                $entity = $this->entityReader->find($entityName, (int) $targetId);
                if (null === $entity) {
                    $output->writeln(sprintf('The entity <error>"%s"</error> with id <error>"%s"</error> specified for export, does not exist in DB!', $entityName, $targetId));
                    dd();
                }
                try {
                    $this->process($entity);
                } catch (CommandException $e) {
                    $output->writeln($e->getMessage());
                }
            }
        }

        foreach ($this->alwaysXport as $class) {
            $this->entityReader->setWorkingClass($class);
            $entities = $this->entityReader->findByRole($class, 'ROLE_SUPER_ADMIN');
            foreach ($entities as $entity) {
                //dd($entity->getId());
                $this->process($entity);
            }
        }

        // we add all embeddables to this->yaml
        $this->transformEmbeddables();
        $this->fixturesWriter->write($this->yaml);
        unset($this->yaml);
        unset($this->embedObjectsToProcess);
    }

    /**
     * @param $entity
     *
     * @throws \Exception
     */
    protected function process($entity): void
    {
        $this->generateYmlFixtures($entity);

        $this->debug($this->processedObjects);
    }

    /**
     * @param $entity
     */
    protected function generateYmlFixtures($entity)
    {
        $entityName = get_class($entity);
        if ($this->debug) {
            echo '___'.PHP_EOL;
            $this->debug(sprintf('### PROCESSING %s, with id=%s', $entityName, $entity->getId()));
        }
        $this->entityReader->setWorkingClass($entityName);

        // mark entity as processed
        $thisObjectKey = $this->createObjectKey($entity->getId(), $entityName);

        if (in_array($thisObjectKey, $this->processedObjects)) {
            return;
        }
        $this->processedObjects[] = $thisObjectKey;

        $yamlKey = str_replace('Proxies\\__CG__\\', '', $entityName);

        // extract entity fields
        list($element, $relatedToBeRemoved) = $this->extractFields($entity);

        // extract related entities shortcuts
        list($relatedObjects, $element) = $this->extractRelatedFields($entity, $element);
        if (count($relatedToBeRemoved) > 0) {
            // we remove the keys from elements to avoid circula references
            foreach ($relatedToBeRemoved as $keyToRemove) {
                unset($element[$keyToRemove]);
            }
        }

        $addKey = str_replace('@', '', $thisObjectKey);
        $this->yaml[$yamlKey][$addKey] = $element;

        // iterate the relations to extract their data
        foreach ($relatedObjects as $objectToProcess) {
            $this->generateYmlFixtures($objectToProcess);
        }

        if ($this->debug) {
            echo PHP_EOL;
        }
    }

    /**
     * @param $param
     */
    protected function debug($param): void
    {
        if ($this->debug) {
            dump($param);
        }
    }

    /**
     * @param $entity
     */
    protected function extractFields($entity): array
    {
        $fields = $this->entityReader->getFields();
        $fieldsResults = [];
        foreach ($fields as $field) {
            if (
                'id' === $field
                && !in_array(get_class($entity), $this->keepIdFor)
                && !in_array(get_class($entity),
                    array_map(function ($value) { return 'Proxies\\__CG__\\'.$value; }, $this->keepIdFor)
                )
            ) {
                continue;
            }

            $fieldMapping = $this->entityReader->getFieldMapping($field);

            if (
                array_key_exists('declaredField', $fieldMapping)
                && array_key_exists('originalField', $fieldMapping)
            ) {
                //we deal with an embeddable @object
                $value = $this->createObjectKey($entity->getId(), $fieldMapping['originalClass']);

                if (!array_key_exists($value, $this->embedObjectsToProcess)) {
                    $embeddableGet = 'get'.$fieldMapping['declaredField'];
                    if (method_exists($entity, $embeddableGet)) {
                        $embeddableObject = $entity->$embeddableGet();
                    }
                    $this->embedObjectsToProcess[$value] = $embeddableObject;
                    $key = $fieldMapping['declaredField'];
                }
            }

            if (!array_key_exists('declaredField', $fieldMapping)) {
                $key = $field;
                $value = $this->handleNormalField($entity, $field, $fieldMapping);
            }

            // set field value to an embed @object
            $fieldsResults[$key] = $value;

            unset($value);
        }

        // we will add a __construct key
        $relatedToBeRemoved = [];
        $reflectionClass = new ReflectionClass($entity);
        $method_name = '__construct';
        if ($reflectionClass->hasMethod('__construct')) {
            $method = new ReflectionMethod($entity, '__construct');
            if (count($method->getParameters())) {
                $realParams = array_filter($method->getParameters(), function ($value) {
                    return 'Closure' !== $value->getType()->getName();
                });
                if (count($realParams) > 0) {
                    foreach ($realParams as $param) {
                        $keyToRemove = $param->getName();
                        //$fieldsResults[$key] = $value;
//                        dump(get_class($entity));
//                        dump($entity->getId());
//                        dump($entity->getOrderItem()->getId());
                        $constructorObject = $entity->getOrderItem();
                        $constructorClass = get_class($entity->getOrderItem());
                        $constructorObjectId = $constructorObject->getId();
                        $thisObjectKey = $this->createObjectKey($constructorObjectId, $constructorClass);
//                        dump($thisObjectKey);
//                        dump($fieldsResults);
                        $fieldsResults[$method_name][] = $thisObjectKey;
                        $relatedToBeRemoved[] = $keyToRemove;
                    }
                    //dd($realParams);
                }
            }
        }

        return [$fieldsResults, $relatedToBeRemoved];
    }

    /**
     * @param $entity
     */
    protected function extractRelatedFields($entity, array $element): array
    {
        // process related objects
        $relatedObjects = [];
        foreach ($this->entityReader->getRelations() as $relation) {

            $values = [];
            if (in_array($relation['type'], [ClassMetadataInfo::ONE_TO_ONE, ClassMetadataInfo::MANY_TO_ONE])) {
                $values = null;
            }

            $this->debug('to iterate '.$relation['targetEntity'].' relations.');

            $searchField = 'get'.ucfirst($relation['fieldName']);
            $collection = $entity->$searchField();
            if ('getRoles' == $searchField) {
                $collection = $entity->$searchField(true);
            }

            if ($collection) {
                if (in_array(get_class($collection), [
                    'Doctrine\Common\Collections\ArrayCollection',
                    'Doctrine\ORM\PersistentCollection',
                ])) {
                    foreach ($collection as $object) {
                        if (is_object($object)) {
                            if (method_exists($object, 'getId')) {
                                $id = $object->getId();
                            }
                            if ('Closure' == get_class($object)) {
                                $id = $collection->getId();
                            }
                            list($newRelatedObjectKey, $values, $relatedObjects) = $this->prepareValuesAndRelatedObjects($relation['targetEntity'],
                                $id, $values, $relatedObjects);
                        }
                    }
                } elseif (is_object($collection)) {
                    $id = $collection->getId();
                    list($newRelatedObjectKey, $values, $relatedObjects) = $this->prepareValuesAndRelatedObjects($relation['targetEntity'],
                        $id, $values, $relatedObjects);
                }

                if ($relation['isOwningSide']) {
                    // we allowed processing of the not owning side as related objects but we do not present this as a fixture property
                    $element[$relation['fieldName']] = $values;
                }

                unset($newRelatedObjectKey);
            }
        }

        return [$relatedObjects, $element];
    }

    /**
     * @param $str
     *
     * @return string
     */
    public function toCamelCase($str)
    {
        $str[0] = strtoupper($str[0]);

        $func = create_function('$c', 'return strtoupper($c[1]);');

        return preg_replace_callback('/_([a-z])/', $func, $str);
    }

    protected function prepareValuesAndRelatedObjects(string $entityName, int $id, $values, array $relatedObjects): array
    {
        $newRelatedObjectKey = $this->createObjectKey($id, $entityName);

        if (is_array($values) && !in_array($newRelatedObjectKey, $values)) {
            $values[] = $newRelatedObjectKey;
        }
        if (!$values) {
            $values = $newRelatedObjectKey;
        }

        if (!in_array($newRelatedObjectKey, $this->processedObjects)) {
            $relatedObjects[] = $this->entityReader->find($entityName, $id);
        }

        return [$newRelatedObjectKey, $values, $relatedObjects];
    }

    protected function createObjectKey(int $id, string $entityName): string
    {
        $thisObjectKey = '@'.str_replace('\\', '_',
                str_replace('Proxies\\__CG__\\', '', $entityName)).'_'.$id;

        return $thisObjectKey;
    }

    /**
     * @param $entity
     * @param $field
     * @param $fieldMappin
     *
     * @return string|array|null
     */
    protected function handleNormalField($entity, $field, $fieldMapping)
    {
        //we deal with a normal field
        $methodName = 'get'.$this->toCamelCase($field);
        $altMethodName = 'is'.$this->toCamelCase($field);
        $otherAltMethodName = lcfirst($this->toCamelCase($field));

        $reflection = new ReflectionMethod($entity, $methodName);
        if (!$reflection->isPublic()) {
            return null;
        }

        if (method_exists($entity, $methodName)) {
            $value = $entity->$methodName();
        } elseif (method_exists($entity, $altMethodName)) {
            $value = $entity->$altMethodName();
        }
        if (!isset($value) && method_exists($entity, $otherAltMethodName)) {
            $value = $entity->$otherAltMethodName();
        }

        $value = $this->fixIfPasswordField($entity, $field, $value);

        //$fieldType = $this->entityReader->getFieldType(lcfirst($this->toCamelCase($field)));
        $fieldType = $fieldMapping['type'];
        if ((('datetime' === $fieldType) || ('date' === $fieldType)) && $value) {
            $value = '<dateTimeBetween("'.$value->format('Y-m-d H:i:sP').'", "'.$value->format('Y-m-d H:i:sP').'")>';
        }

        return isset($value) ? $value : null;
    }

    /**
     * Transform array with embeddabkle objects in array of property values.
     */
    protected function transformEmbeddables()
    {
        foreach ($this->embedObjectsToProcess as $key => $object) {
            $entity = get_class($object);
            $this->entityReader->setWorkingClass($entity);
            $fieldsResults = [];
            foreach ($this->entityReader->getFields() as $field) {
                $fieldMapping = $this->entityReader->getFieldMapping($field);

                $value = $this->handleNormalField($object, $field, $fieldMapping);
                $fieldsResults[$field] = $value;
            }

            $yamlKey = get_class($object);
            $this->yaml[$yamlKey][str_replace('@', '', $key)] = $fieldsResults;
        }
    }

    /**
     * Password field value fix.
     *
     * @param $entity
     * @param $field
     * @param $value
     *
     * @return mixed|string|string[]
     */
    protected function fixIfPasswordField($entity, $field, $value)
    {
        if (
            (
                'App\Entity\User\ShopUser' === get_class($entity)
                || 'App\Entity\User\AdminUser' === get_class($entity)
            )
            && 'password' == $field
        ) {
            $value = str_replace('$', '\$', $value);
        }

        return $value;
    }
}
