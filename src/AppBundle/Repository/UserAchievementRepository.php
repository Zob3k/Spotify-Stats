<?php

namespace AppBundle\Repository;

use Doctrine\ORM\EntityRepository;

/**
 * UserAchievementRepository
 *
 * This class was generated by the PhpStorm "Php Annotations" Plugin. Add your own custom
 * repository methods below.
 */
class UserAchievementRepository extends EntityRepository
{

    public function updateAchievementsToUnlock($id, $rules) {
        $cond = "";
        foreach ($rules as $key => $rule) {
            switch($rule['t']) {
                case "ps":
                    $cond .= "
                        JOIN played__song ps".$key." ON u.id = ps".$key.".user_id AND ps".$key.".".$rule['n']." ".$rule['s']." ".$rule['v']."
                        ";
                    break;
                case "s":
                    $cond .= "
                        JOIN played__song ps".$key." ON u.id = ps".$key.".user_id 
                        JOIN song s".$key." ON ps".$key.".song_id = s".$key.".id AND s".$key.".".$rule['n']." ".$rule['s']." ".$rule['v']."
                        ";
                    break;
                case "ss":
                    $cond .= "
                        JOIN played__song ps".$key." ON u.id = ps".$key.".user_id 
                        JOIN song s".$key." ON ps".$key.".song_id = s".$key.".id 
                        JOIN song_stats ss".$key." ON ss".$key.".id = s".$key.".stats_id AND ss".$key.".".$rule['n']." ".$rule['s']." ".$rule['v']."
                        ";
                    break;
                case "al":
                    $cond .= "
                        JOIN played__song ps".$key." ON u.id = ps".$key.".user_id 
                        JOIN song s".$key." ON ps".$key.".song_id = s".$key.".id 
                        JOIN album al".$key." ON s".$key.".album_id = al".$key.".id AND al".$key.".".$rule['n']." ".$rule['s']." ".$rule['v']."
                        ";
                    break;
                case "ar":
                    $cond .= "
                        JOIN played__song ps".$key." ON u.id = ps".$key.".user_id 
                        JOIN song s".$key." ON ps".$key.".song_id = s".$key.".id 
                        JOIN artist ar".$key." ON s".$key.".artist_id = ar".$key.".id AND ar".$key.".".$rule['n']." ".$rule['s']." ".$rule['v']."
                        ";
                    break;
            }
        }
        $sql = <<< SQL
        INSERT IGNORE INTO user__achievement(user_id, achievement_id, unlocked_at)  
        SELECT u.id, $id, NOW()
        FROM user u 
        $cond
        WHERE u.id NOT IN (SELECT user_id FROM user__achievement WHERE achievement_id = $id )
        GROUP BY u.id;
SQL;
        $this->getEntityManager()->getConnection()->query($sql)->execute();
    }

}