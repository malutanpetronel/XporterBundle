<?php

namespace Aquis\XporterBundle\Service\FixtureDump;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManager;

/**
 * Class EntityReader.
 *
 * @copyright Aquis Grana impex srl (http://www.webnou.ro/)
 * @author    Petronel Malutan <malutanpetronel@gmail.com>
 */
class EntityReader
{
    /**
     * @var string
     */
    protected $entityClassName;

    /**
     * EntityReader constructor.
     */
    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * @param string $entityClassName
     */
    public function setWorkingClass(string $entityClassName)
    {
        $this->entityClassName = $entityClassName;
    }

    /**
     * Extract field names for entity.
     *
     * @return array
     */
    public function getFields()
    {
        $fields = $this->entityManager->getClassMetadata($this->entityClassName)->getFieldNames();

        return $fields;
    }

    /**
     * Extract field type.
     *
     * @param $field
     *
     * @return mixed
     *
     * @throws \Doctrine\ORM\Mapping\MappingException
     */
    public function getFieldMapping($field)
    {
        $fieldInfo = $this->entityManager->getClassMetadata($this->entityClassName)->getFieldMapping($field);

        return $fieldInfo;
    }

    /**
     * Read all Entities name.
     *
     * @return string[]
     *
     * @throws \Doctrine\ORM\ORMException
     */
    public function getAllEntities()
    {
        $entities = $this->entityManager->getConfiguration()->getMetadataDriverImpl()->getAllClassNames();

        return $entities;
    }

    /**
     * Find current class object by id.
     *
     * @return object|null
     */
    public function find(string $entityClassName, int $id)
    {
        return $this->entityManager->getRepository($entityClassName)->find($id);
    }

    /**
     * Find current class all objects.
     *
     * @return Collection|null
     */
    public function findAll(string $entityClassName)
    {
        return $this->entityManager->getRepository($entityClassName)->findAll();
    }

    /**
     * Find users with role
     *
     * @return Collection|null
     */
    public function findByRole(string $entityClassName, string $role) {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('u')
            ->from($entityClassName, 'u')
            ->leftJoin('u.roles', 'r')
            ->where('r.name LIKE :role')
            ->setParameter('role', '%'. $role.'%');

        return $qb->getQuery()->getResult();
    }

    /**
     * Return current class relations.
     *
     * @return array
     */
    public function getRelations()
    {
        $classMetaData = $this->entityManager->getClassMetadata($this->entityClassName);

        return $classMetaData->getAssociationMappings();
    }
}
