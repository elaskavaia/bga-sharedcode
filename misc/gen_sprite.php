<?php
// this simple script generates sprite css
// it has no params - fix inline to change what it generates

$from=1;
$to=40+4;
$maxcol=4;
$scol=$maxcol-1;
$srow=((int)(($to-$from)/$maxcol));
if ($srow==0) $srow=1;
for ($num=$from;$num<=$to;$num++) {
    $index=$num-$from;
    $row=(int)($index/$maxcol);
    $col=$index%$maxcol;
    echo ".tech_E_$num { background-position: calc(100% / $scol * $col) calc(100% / $srow * $row);}\n";
}
?>