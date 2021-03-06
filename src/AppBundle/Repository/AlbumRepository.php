<?php

namespace AppBundle\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\ResultSetMapping;

/**
 * AlbumRepository
 *
 * This class was generated by the PhpStorm "Php Annotations" Plugin. Add your own custom
 * repository methods below.
 */
class AlbumRepository extends EntityRepository
{

    public function getAllIds() {
        $rsm = new ResultSetMapping();
        $rsm->addScalarResult("id", "id");
        $sql = <<< SQL
            SELECT a.album_id id
            FROM album a
SQL;
        return $this->getEntityManager()
            ->createNativeQuery($sql, $rsm)
            ->getScalarResult();
    }

}
