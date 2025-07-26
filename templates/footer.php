<?php
// Determine the base path to correctly link assets and pages from any directory level
$path_prefix = str_repeat('../', substr_count(str_replace('\\', '/', __DIR__), '/'));
?>
    <footer id="main-footer">
        <p>Copyright &copy; <?php echo date('Y'); ?> VuaToFua</p>
    </footer>
</body>
</html>
