<?php
include 'db.php';
$r = $conn->query('DESC investments');
while($f = $r->fetch_assoc()) echo $f['Field'].' - '.$f['Type'].PHP_EOL;
