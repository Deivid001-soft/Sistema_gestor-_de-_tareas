</div>
    </main>
    
    <div id="alertContainer"></div>
    
    <script src="../js/app.js"></script>
    <?php if (isset($additional_scripts)): ?>
        <?php foreach ($additional_scripts as $script): ?>
            <script src="<?php echo $script; ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>