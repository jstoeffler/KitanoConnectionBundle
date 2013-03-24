<?php

namespace Kitano\ConnectionBundle\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\EntityManager;

use Kitano\ConnectionBundle\Model\ConnectionInterface;
use Kitano\ConnectionBundle\Proxy\DoctrineOrmConnection;
use Kitano\ConnectionBundle\Model\NodeInterface;
use Kitano\ConnectionBundle\Exception\NotSupportedNodeException;

/**
 * ConnectionRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class DoctrineOrmConnectionRepository extends EntityRepository implements ConnectionRepositoryInterface
{
    /**
     * @var string
     */
    protected $class;

    public function __construct(EntityManager $em, $class)
    {
        $metadata = $em->getClassMetadata($class);
        parent::__construct($em, $metadata);

        $this->class = $class;
    }

    /**
     * @param NodeInterface $node
     * @param array         $filters
     *
     * @return array
     */
    public function getConnectionsWithSource(NodeInterface $node, array $filters = array())
    {
        $objectInformations = $this->extractMetadata($node);

        $objectClass = $objectInformations["object_class"];
        $objectId = $objectInformations["object_id"];

        $queryBuilder = $this->createQueryBuilder("connection");
        $queryBuilder->where("connection.sourceObjectClass = :objectClass");
        $queryBuilder->andWhere("connection.sourceObjectId = :objectId");
        $queryBuilder->setParameter("objectClass", $objectClass);
        $queryBuilder->setParameter("objectId", $objectId);

        if (array_key_exists('type', $filters)) {
            $queryBuilder->andWhere("connection.type = :type");
            $queryBuilder->setParameter("type", $filters['type']);
        }

        $connections = $queryBuilder->getQuery()->getResult();

        foreach ($connections as $connection) {
            $this->fillConnection($connection);
        }

        return $connections;
    }

    /**
     * @param \Kitano\ConnectionBundle\Model\NodeInterface $node
     * @param array                                        $filters
     *
     * @return array
     */
    public function getConnectionsWithDestination(NodeInterface $node, array $filters = array())
    {
        $objectInformations = $this->extractMetadata($node);

        $objectClass = $objectInformations["object_class"];
        $objectId = $objectInformations["object_id"];

        $queryBuilder = $this->createQueryBuilder("connection");
        $queryBuilder->where("connection.destinationObjectClass = :objectClass");
        $queryBuilder->andWhere("connection.destinationObjectId = :objectId");
        $queryBuilder->setParameter("objectClass", $objectClass);
        $queryBuilder->setParameter("objectId", $objectId);

        if (array_key_exists('type', $filters)) {
            $queryBuilder->andWhere("connection.type = :type");
            $queryBuilder->setParameter("type", $filters['type']);
        }

        $connections = $queryBuilder->getQuery()->getResult();

        foreach ($connections as $connection) {
            $this->fillConnection($connection);
        }

        return $connections;
    }

    /**
     * @param \Kitano\ConnectionBundle\Model\NodeInterface $node
     * @param array $filters
     * @return array
     */
    public function getConnections(NodeInterface $node, array $filters = array())
    {
        $nodeInformations = $this->extractMetadata($node);

        $qb = $this->createQueryBuilder('c');

        $qb->select('c')
            ->where(
                $qb->expr()->orX(
                    $qb->expr()->andX("c.sourceObjectId = :nodeId", "c.sourceObjectClass = :nodeClass"),
                    $qb->expr()->andX("c.destinationObjectId = :nodeId", "c.destinationObjectClass = :nodeClass")
                )
            )
        ->setParameters(array(
            'nodeClass' => $nodeInformations['object_class'],
            'nodeId' => $nodeInformations['object_id'],
        ));

        if (array_key_exists('type', $filters)) {
            $qb->andWhere("c.type = :type");
            $qb->setParameter("type", $filters['type']);
        }

        $connections = $qb->getQuery()->getResult();

        foreach ($connections as $connection) {
            $this->fillConnection($connection);
        }

        return $connections;
    }

    /**
     * @param \Kitano\ConnectionBundle\Model\NodeInterface $source
     * @param \Kitano\ConnectionBundle\Model\NodeInterface $destination
     * @param array $filters
     * @return bool
     */
    public function areConnected(NodeInterface $source, NodeInterface $destination, array $filters = array())
    {
        $node1Informations = $this->extractMetadata($source);
        $node2Informations = $this->extractMetadata($destination);

        $qb = $this->createQueryBuilder('c');

        $qb->select('COUNT (c)')
            ->where(
                $qb->expr()->andX(
                    $qb->expr()->andX("c.sourceObjectId = :node1Id", "c.sourceObjectClass = :node1Class"),
                    $qb->expr()->andX("c.destinationObjectId = :node2Id", "c.destinationObjectClass = :node2Class")
                )
            )
        ->setParameters(array(
            'node1Class' => $node1Informations['object_class'],
            'node2Class' => $node2Informations['object_class'],
            'node1Id' => $node1Informations['object_id'],
            'node2Id' => $node2Informations['object_id'],
        ));

        if (array_key_exists('type', $filters)) {
            $qb->andWhere("c.type = :type");
            $qb->setParameter("type", $filters['type']);
        }

        return ($qb->getQuery()->getSingleScalarResult() > 0) ? true : false;
    }

    /**
     * @param ConnectionInterface $connection
     *
     * @return ConnectionInterface
     */
    public function update(ConnectionInterface $connection)
    {
        $sourceInformations = $this->extractMetadata($connection->getSource());
        $destinationInformations = $this->extractMetadata($connection->getDestination());

        $connection->setSourceObjectId($sourceInformations["object_id"]);
        $connection->setSourceObjectClass($sourceInformations["object_class"]);
        $connection->setDestinationObjectId($destinationInformations["object_id"]);
        $connection->setDestinationObjectClass($destinationInformations["object_class"]);

        $this->_em->persist($connection);
        $this->_em->flush();

        return $connection;
    }

    /**
     * @param ConnectionInterface $connection
     *
     * @return ConnectionRepositoryInterface
     */
    public function destroy(ConnectionInterface $connection)
    {
        $this->_em->remove($connection);
        $this->_em->flush();

        return $this;
    }

    /**
     * @return ConnectionInterface
     */
    public function createEmptyConnection()
    {
        return new $this->class();
    }

    /**
     * @param NodeInterface $node
     *
     * @return array
     */
    protected function extractMetadata(NodeInterface $node)
    {
        $classMetadata = $this->_em->getClassMetadata(get_class($node));

        $ids = $classMetadata->getIdentifierValues($node);

        if (count($ids) > 1) {
            throw new NotSupportedNodeException("Composed primary keys for: " . $classMetadata->getName());
        }

        return array(
            'object_class' => $classMetadata->getName(),
            'object_id' => array_pop($ids),
        );
    }

    /**
     * @param DoctrineOrmConnection $connection
     *
     * @return DoctrineOrmConnection
     */
    protected function fillConnection(DoctrineOrmConnection $connection)
    {
        $source = $this->_em->getRepository($connection->getSourceObjectClass())->find($connection->getSourceObjectId());
        $destination = $this->_em->getRepository($connection->getDestinationObjectClass())->find($connection->getDestinationObjectId());

        $connection->setSource($source);
        $connection->setDestination($destination);

        return $connection;
    }
}
