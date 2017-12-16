#!/bin/sh
cd /volume2/ScriptsMaison/20140421_simpleFindDuplicate;
export OPENSSL_PATH=/bin/;
./putFileSHA1InCSVFile.php --path-to-fetch=/volume2/Multimedia/ --reference-csv-file=20161128_duplicate_v2_a_classer.csv --destination-csv-file=20161128_duplicate_v3_a_classer.csv
echo "Fin launcher putFileSHA1InCSVFile";