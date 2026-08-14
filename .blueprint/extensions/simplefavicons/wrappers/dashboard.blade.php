<?php 
    $file_path = $blueprint->dbGet('simplefavicons', 'file_path');
    $Status = $blueprint->dbGet('simplefavicons', 'status');
?>

@if("$Status" == "true")
<script> 

document.head = document.head || document.getElementsByTagName('head')[0];

function changeFavicon() {
 var link = document.createElement('link'),
     oldLink = document.getElementById('dynamic-favicon');
 link.id = 'dynamic-favicon';
 link.rel = 'shortcut icon';
 link.href = "<?php echo $file_path;?>";
 if (oldLink) {
  document.head.removeChild(oldLink);
 }
 var favicons = document.querySelectorAll("link[rel='shortcut icon'], link[rel='icon']");
 for(var i = 0; i < favicons.length; i++){
  document.head.removeChild(favicons[i]);
 }
 document.head.appendChild(link);
}

changeFavicon();

</script>
@endif
