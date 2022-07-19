echo Clone monsite4g
mysqldump monsite4g -u root -proot > monsite4g.sql
mysql monsite4g_anfr -u root -proot < monsite4g.sql
