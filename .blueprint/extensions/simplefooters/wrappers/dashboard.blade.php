<?php
    $Status = $blueprint->dbGet('simplefooters', 'status');
    $Text = $blueprint->dbGet('simplefooters', 'text');
    $FontSize = $blueprint->dbGet('simplefooters', 'fontsize');
    $FontColor = $blueprint->dbGet('simplefooters', 'fontcolor');
?>

@if("$Status" == "true")
<p class="Footer"><?php echo $Text?></p>
@endif
<p class="PteroFooter"><a rel="noopener nofollow noreferrer" href="https://pterodactyl.io" target="_blank" class="PageContentBlock___StyledA-sc-kbxq2g-4 eOGAqX">Pterodactyl®</a>&nbsp;© 2015 - 2023</p>


<style id="footer">

.Footer{
    text-align: center;
    color: {{$FontColor}};
    font-size: {{$FontSize}};
    line-height: 1rem;
    content: "before";
  }

.PteroFooter{
    text-align: center;
    --tw-text-opacity: 1;
    padding-top: 10px;
    color: hsla(211,12%,43%,var(--tw-text-opacity));
    font-size: 0.75rem;
    line-height: 1rem;
}

.dcHyfd{
   display: none;
}
</style>