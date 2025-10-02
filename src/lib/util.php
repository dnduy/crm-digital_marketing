<?php
// ==========================
// FILE: /lib/util.php
// ==========================
function paginate($page,$per,$total){ $pages=max(1, (int)ceil($total/$per)); $page=max(1,min($page,$pages)); return [$page,$pages]; }
function pagenav($page,$pages,$baseParams=[]){ echo '<div style="margin:8px 0">'; for($i=1;$i<=$pages;$i++){ $q=http_build_query(array_merge($_GET,$baseParams,['page'=>$i])); $cls=$i===$page?'btn':'btn secondary'; echo '<a class="'.$cls.'" style="margin-right:4px" href="?'.$q.'">'.$i.'</a>'; } echo '</div>'; }
?>