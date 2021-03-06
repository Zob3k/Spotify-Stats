<?php

namespace AppBundle\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\ResultSetMapping;

/**
 * UserRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class UserRepository extends EntityRepository
{

    public function countPlayedSongs($userId) {
        $rsm = new ResultSetMapping();
        $rsm->addScalarResult("count", "count");
        return $this->getEntityManager()->createNativeQuery("SELECT COUNT(*) count FROM played__song WHERE user_id = ?", $rsm) ->setParameters(array($userId))->getSingleScalarResult();
    }

    public function getGenresListenedByUser($userId) {
        $rsm = new ResultSetMapping();
        $rsm->addScalarResult("count", "count");
        $rsm->addScalarResult("name", "name");
        $sql = <<< SQL
        SELECT genre.name, SUM(played__song.count) count
        FROM played__song
        JOIN song ON played__song.song_id = song.id
        JOIN artist ON song.artist_id = artist.id
        JOIN artist_genre ON artist.id = artist_genre.artist_id
        JOIN genre ON artist_genre.genre_id = genre.id
        WHERE played__song.user_id = ? 
        GROUP BY genre.id
        ORDER BY count DESC
SQL;
        return $this->getEntityManager()->createNativeQuery($sql, $rsm) ->setParameters(array($userId))->getScalarResult();
    }


    public function getTopGenresByUser($userId) {
        $rsm = new ResultSetMapping();
        $rsm->addScalarResult("name", "name");
        $sql = <<< SQL
        SELECT genre.name, SUM(played__song.count) count
        FROM played__song
        JOIN song ON played__song.song_id = song.id
        JOIN artist ON song.artist_id = artist.id
        JOIN artist_genre ON artist.id = artist_genre.artist_id
        JOIN genre ON artist_genre.genre_id = genre.id
        WHERE played__song.user_id = ?
        GROUP BY genre.id
        ORDER BY count DESC
        LIMIT 5
SQL;
        return $this->getEntityManager()->createNativeQuery($sql, $rsm) ->setParameters(array($userId))->getScalarResult();
    }
}
