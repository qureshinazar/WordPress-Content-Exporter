<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WordPress Content Exporter</title>
    <link rel="stylesheet" href="templates/styles.css">
</head>
<body>
    <div class="container">
        <h1>WordPress Content Exporter</h1>
        
        <?php if ($message): ?>
            <div class="message <?php 
                if (strpos($message, '✅') !== false) echo 'success';
                elseif (strpos($message, '❌') !== false) echo 'error';
                else echo 'warning';
            ?>">
                <?php echo $message ?>
            </div>
        <?php endif; ?>
        
        <form method="post">
            <div class="section">
                <div class="section-title">Database Connection</div>
                
                <div class="form-group">
                    <label for="db_host">Database Host:</label>
                    <input type="text" id="db_host" name="db_host" value="<?php echo htmlspecialchars($values['db_host']) ?>" required>
                    <div class="help-text">Usually 'localhost' or an IP address</div>
                </div>
                
                <div class="form-group">
                    <label for="db_name">Database Name:</label>
                    <input type="text" id="db_name" name="db_name" value="<?php echo htmlspecialchars($values['db_name']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="db_user">Database Username:</label>
                    <input type="text" id="db_user" name="db_user" value="<?php echo htmlspecialchars($values['db_user']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="db_pass">Database Password:</label>
                    <input type="password" id="db_pass" name="db_pass" value="<?php echo htmlspecialchars($values['db_pass']) ?>">
                </div>
                
                <div class="form-group">
                    <label for="table_prefix">Table Prefix:</label>
                    <input type="text" id="table_prefix" name="table_prefix" value="<?php echo htmlspecialchars($values['table_prefix']) ?>" required>
                    <div class="help-text">Default is 'wp_'. Find this in your wp-config.php file.</div>
                </div>
            </div>
            
            <div class="section">
                <div class="section-title">Export Settings</div>
                
                <div class="form-group">
                    <label for="output_dir">Output Directory:</label>
                    <input type="text" id="output_dir" name="output_dir" value="<?php echo htmlspecialchars($values['output_dir']) ?>" required>
                    <div class="help-text">Absolute server path where exported files will be saved</div>
                </div>
                
                <div class="form-group">
                    <label>Export Format:</label>
                    <select id="export_format" name="export_format">
                        <option value="html" <?php echo $values['export_format'] === 'html' ? 'selected' : '' ?>>HTML</option>
                        <option value="txt" <?php echo $values['export_format'] === 'txt' ? 'selected' : '' ?>>Plain Text</option>
                    </select>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" id="export_pages" name="export_pages" value="1" <?php echo $values['export_pages'] === '1' ? 'checked' : '' ?>>
                    <label for="export_pages">Include Pages</label>
                </div>
            </div>
            
            <button type="submit">Start Export</button>
        </form>
    </div>
</body>
</html>