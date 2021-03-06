<?php

namespace AppBundle\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\ResultSetMapping;

/**
 * SongRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class SongRepository extends EntityRepository
{

    public function getLastPouplarityValues() {
        $rsm = new ResultSetMapping();
        $rsm->addScalarResult("song_id", "song_id");
        $rsm->addScalarResult("popularity", "popularity");
        $sql = <<< SQL
            SELECT p.song_id, p.popularity
            FROM song__popularity p
            JOIN ( SELECT MAX(created_at) created_at, song_id FROM song__popularity GROUP BY song_id) p2 ON p.song_id = p2.song_id AND p.created_at = p2.created_at
SQL;
        return $this->getEntityManager()
            ->createNativeQuery($sql, $rsm)
            ->getScalarResult();
    }


    public function getTopSongsByUser($userId) {
        $rsm = new ResultSetMapping();
        $rsm->addScalarResult("song_id", "song_id");
        $sql = <<< SQL
            SELECT p.song_id
            FROM played__song p
            WHERE p.user_id = ?
            ORDER BY count DESC, p.id DESC 
            LIMIT 50
SQL;
        $ids = $this->getEntityManager()
            ->createNativeQuery($sql, $rsm)
            ->setParameters([$userId])
            ->getScalarResult();
        return $this->findBy(["id" => array_column($ids, "song_id")]);
    }

}
