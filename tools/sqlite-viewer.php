<?php
// SQLite Viewer SPA - Enhanced Edition
$db_dir = dirname( __DIR__ ) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR;
$db_file = isset($_GET['db']) ? basename($_GET['db']) : $db_dir . 'tradedesk.db';
$current_table = $_GET['table'] ?? null;
$search_term = $_GET['search'] ?? '';
$dark_mode = isset($_GET['dark']) ? filter_var($_GET['dark'], FILTER_VALIDATE_BOOLEAN) : false;
$export_format = $_GET['export'] ?? null;

// Check if database exists
$db_exists = file_exists($db_file);

try {
    $db = $db_exists ? new PDO("sqlite:$db_file") : null;
    $db?->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

// Get all tables
$tables = [];
if ($db_exists && $db) {
    $result = $db->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
    $tables = $result->fetchAll(PDO::FETCH_COLUMN);
}

// Handle exports
if ($export_format && $current_table && in_array($current_table, $tables)) {
    $stmt = $db->query("SELECT * FROM $current_table");
    $table_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($export_format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="'.$current_table.'_export.csv"');
        $output = fopen('php://output', 'w');
        if (!empty($table_data)) {
            fputcsv($output, array_keys($table_data[0]));
            foreach ($table_data as $row) {
                fputcsv($output, $row);
            }
        }
        fclose($output);
        exit;
    } elseif ($export_format === 'json') {
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="'.$current_table.'_export.json"');
        echo json_encode($table_data, JSON_PRETTY_PRINT);
        exit;
    }
}

// Get table data if a table is selected
$table_data = [];
$columns = [];
if ($current_table && in_array($current_table, $tables)) {
    // Build search query
    $query = "SELECT * FROM $current_table";
    $params = [];
    
    if ($search_term) {
        $stmt = $db->query("PRAGMA table_info($current_table)");
        $columns_info = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $search_conditions = [];
        
        foreach ($columns_info as $col) {
            $col_name = $col['name'];
            $search_conditions[] = "$col_name LIKE ?";
            $params[] = "%$search_term%";
        }
        
        if (!empty($search_conditions)) {
            $query .= " WHERE " . implode(" OR ", $search_conditions);
        }
    }
    
    $query .= " LIMIT 500";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $table_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get column info
    $stmt = $db->query("PRAGMA table_info($current_table)");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Run custom query if submitted
$custom_query = $_POST['custom_query'] ?? null;
$query_results = null;
$query_error = null;
if ($custom_query && $db) {
    try {
        $stmt = $db->prepare($custom_query);
        $stmt->execute();
        
        // Only fetch if it's a SELECT query
        if (stripos(trim($custom_query), 'select') === 0) {
            $query_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $query_results = ["success" => true, "affected_rows" => $stmt->rowCount()];
        }
    } catch (PDOException $e) {
        $query_error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SQLite Viewer Pro</title>
    <style>
        <?php if ($dark_mode): ?>
        :root {
            --bg-color: #1a1a1a;
            --text-color: #f0f0f0;
            --link-color: rgb(83, 166, 255);
            --link-hover-color: rgb(2, 106, 218);
            --link-visited-color: skyblue;
            --sidebar-bg: #2d2d2d;
            --hover-bg: #3d3d3d;
            --active-bg: #4d4d4d;
            --border-color: #444;
            --th-bg: #333;
            --input-bg: #333;
            --input-text: #fff;
            --pre-bg: #222;
        }
        <?php else: ?>
        :root {
            --bg-color: #ffffff;
            --text-color: #333333;
            --link-color: #0056b3;
            --link-hover-color: #003673;
            --link-visited-color: royalblue;
            --sidebar-bg: #f5f5f5;
            --hover-bg: #e9e9e9;
            --active-bg: #dddddd;
            --border-color: #ddd;
            --th-bg: #f2f2f2;
            --input-bg: #fff;
            --input-text: #333;
            --pre-bg: #f8f8f8;
        }
        <?php endif; ?>
        
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: var(--bg-color);
            color: var(--text-color);
        }

        a {
            color: var(--link-color);
            text-decoration: none;
        }

        a:hover {
            color: var(--link-hover-color);
        }

        a:visited {
            color: var(--link-visited-color);
        }

        .container {
            display: flex;
            gap: 20px;
        }
        
        .sidebar {
            min-width: 280px;
            background-color: var(--sidebar-bg);
            padding: 15px;
            border-radius: 8px;
        }
        
        .main-content {
            flex: 1;
        }
        
        .table-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .table-list li {
            padding: 8px 12px;
            cursor: pointer;
            border-radius: 4px;
            margin-bottom: 4px;
        }
        
        .table-list li:hover {
            background-color: var(--hover-bg);
        }
        
        .table-list li.active {
            font-weight: bold;
            background-color: var(--active-bg);
        }
        
        table {
            border-collapse: collapse;
            width: 100%;
            margin-top: 15px;
            border: 1px solid var(--border-color);
        }
        
        th, td {
            border: 1px solid var(--border-color);
            padding: 10px;
            text-align: left;
        }
        
        th {
            background-color: var(--th-bg);
            position: sticky;
            top: 0;
        }
        
        .error {
            color: #ff6b6b;
            padding: 10px;
            background-color: rgba(255, 0, 0, 0.1);
            border-radius: 4px;
        }
        
        .schema {
            background-color: var(--pre-bg);
            padding: 15px;
            margin-top: 20px;
            border-radius: 8px;
            overflow-x: auto;
        }
        
        .toolbar {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .btn {
            padding: 8px 12px;
            background-color: #4a6fa5;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
        }
        
        .btn:hover {
            background-color: #3a5a8f;
        }
        
        .btn-export {
            background-color: #28a745;
        }
        
        .btn-export:hover {
            background-color: #218838;
        }
        
        .btn-dark, .btn-dark:visited {
            color: white;
        }
        
        .btn-dark:hover {
            background-color: silver;
        }
        
        input[type="text"], textarea {
            padding: 8px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            background-color: var(--input-bg);
            color: var(--input-text);
        }
        
        .search-box {
            flex-grow: 1;
        }
        
        .query-results {
            margin-top: 20px;
        }
        
        .tab-container {
            margin-top: 20px;
        }
        
        .tab-buttons {
            display: flex;
            border-bottom: 1px solid var(--border-color);
        }
        
        .tab-button {
            padding: 10px 15px;
            cursor: pointer;
            background: none;
            border: none;
            color: var(--text-color);
            border-bottom: 2px solid transparent;
        }
        
        .tab-button.active {
            border-bottom: 2px solid #4a6fa5;
            font-weight: bold;
        }
        
        .tab-content {
            display: none;
            padding: 15px 0;
        }
        
        .tab-content.active {
            display: block;
        }
        
        pre {
            margin: 0;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        
        .sql-editor {
            width: 100%;
            min-height: 100px;
            font-family: monospace;
        }
        
        .status {
            margin-top: 10px;
            font-style: italic;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <h1>SQLite Viewer Pro</h1>
    
    <div class="container">
        <div class="sidebar">
            <h3>Database: <?= htmlspecialchars($db_file) ?></h3>
            
            <?php if (!$db_exists): ?>
                <p class="error">Database file not found.</p>
            <?php elseif (isset($error)): ?>
                <p class="error"><?= htmlspecialchars($error) ?></p>
            <?php elseif (empty($tables)): ?>
                <p>No tables found in database.</p>
            <?php else: ?>
                <ul class="table-list">
                    <?php foreach ($tables as $table): ?>
                        <li <?= $table === $current_table ? 'class="active"' : '' ?>>
                            <a href="?db=<?= urlencode($db_file) ?>&table=<?= urlencode($table) ?>&dark=<?= $dark_mode ? 'true' : 'false' ?>">
                                <?= htmlspecialchars($table) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            
            <div style="margin-top: 20px;">
                <a href="?db=<?= urlencode($db_file) ?>&dark=<?= !$dark_mode ? 'true' : 'false' ?><?= $current_table ? '&table='.urlencode($current_table) : '' ?>"
                   class="btn btn-dark">
                    <?= $dark_mode ? '☀️ Light Mode' : '🌙 Dark Mode' ?>
                </a>
            </div>
        </div>
        
        <div class="main-content">
            <?php if ($current_table && in_array($current_table, $tables)): ?>
                <div class="toolbar">
                    <form method="get" class="search-box">
                        <input type="hidden" name="db" value="<?= htmlspecialchars($db_file) ?>">
                        <input type="hidden" name="table" value="<?= htmlspecialchars($current_table) ?>">
                        <input type="hidden" name="dark" value="<?= $dark_mode ? 'true' : 'false' ?>">
                        <input type="text" name="search" value="<?= htmlspecialchars($search_term) ?>" 
                               placeholder="Search in table...">
                        <button type="submit" class="btn">Search</button>
                        <?php if ($search_term): ?>
                            <a href="?db=<?= urlencode($db_file) ?>&table=<?= urlencode($current_table) ?>&dark=<?= $dark_mode ? 'true' : 'false' ?>" 
                               class="btn">Clear</a>
                        <?php endif; ?>
                    </form>
                    
                    <div>
                        <a href="?db=<?= urlencode($db_file) ?>&table=<?= urlencode($current_table) ?>&export=csv&dark=<?= $dark_mode ? 'true' : 'false' ?>" 
                           class="btn btn-export">Export CSV</a>
                        <a href="?db=<?= urlencode($db_file) ?>&table=<?= urlencode($current_table) ?>&export=json&dark=<?= $dark_mode ? 'true' : 'false' ?>" 
                           class="btn btn-export">Export JSON</a>
                    </div>
                </div>
                
                <h2><?= htmlspecialchars($current_table) ?></h2>
                
                <?php if (!empty($table_data)): ?>
                    <div style="overflow-x: auto; max-height: 500px; overflow-y: auto;">
                        <table>
                            <thead>
                                <tr>
                                    <?php foreach ($columns as $col): ?>
                                        <th><?= htmlspecialchars($col['name']) ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($table_data as $row): ?>
                                    <tr>
                                        <?php foreach ($columns as $col): ?>
                                            <td><?= htmlspecialchars($row[$col['name']] ?? 'NULL') ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="status">
                        Showing <?= count($table_data) ?> row<?= count($table_data) !== 1 ? 's' : '' ?>
                        <?= $search_term ? 'matching your search' : '' ?>
                    </div>
                <?php else: ?>
                    <p>No data found <?= $search_term ? 'matching your search' : 'in this table' ?>.</p>
                <?php endif; ?>
                
                <div class="schema">
                    <h3>Schema</h3>
                    <pre><?php
                        $stmt = $db->query("SELECT sql FROM sqlite_master WHERE name='$current_table'");
                        echo htmlspecialchars($stmt->fetchColumn());
                    ?></pre>
                </div>
            <?php elseif ($db_exists): ?>
                <p>Select a table from the sidebar to view its contents.</p>
            <?php endif; ?>
            
            <div class="tab-container">
                <div class="tab-buttons">
                    <button class="tab-button active" onclick="openTab(event, 'query-tab')">SQL Query</button>
                </div>
                
                <div id="query-tab" class="tab-content active">
                    <form method="post">
                        <input type="hidden" name="db" value="<?= htmlspecialchars($db_file) ?>">
                        <input type="hidden" name="dark" value="<?= $dark_mode ? 'true' : 'false' ?>">
                        <textarea name="custom_query" class="sql-editor" placeholder="Enter your SQL query here..."><?= htmlspecialchars($custom_query ?? '') ?></textarea>
                        <button type="submit" class="btn">Execute</button>
                    </form>
                    
                    <?php if ($query_error): ?>
                        <div class="error">Error: <?= htmlspecialchars($query_error) ?></div>
                    <?php elseif ($query_results): ?>
                        <div class="query-results">
                            <h3>Results</h3>
                            <?php if (isset($query_results['success'])): ?>
                                <p>Query executed successfully. Affected rows: <?= $query_results['affected_rows'] ?></p>
                            <?php elseif (!empty($query_results)): ?>
                                <div style="overflow-x: auto; max-height: 400px; overflow-y: auto;">
                                    <table>
                                        <thead>
                                            <tr>
                                                <?php foreach (array_keys($query_results[0]) as $col): ?>
                                                    <th><?= htmlspecialchars($col) ?></th>
                                                <?php endforeach; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($query_results as $row): ?>
                                                <tr>
                                                    <?php foreach ($row as $value): ?>
                                                        <td><?= htmlspecialchars($value) ?></td>
                                                    <?php endforeach; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="status">
                                    Showing <?= count($query_results) ?> row<?= count($query_results) !== 1 ? 's' : '' ?>
                                </div>
                            <?php else: ?>
                                <p>Query executed successfully but returned no results.</p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function openTab(evt, tabName) {
            const tabContents = document.getElementsByClassName("tab-content");
            for (let i = 0; i < tabContents.length; i++) {
                tabContents[i].classList.remove("active");
            }
            
            const tabButtons = document.getElementsByClassName("tab-button");
            for (let i = 0; i < tabButtons.length; i++) {
                tabButtons[i].classList.remove("active");
            }
            
            document.getElementById(tabName).classList.add("active");
            evt.currentTarget.classList.add("active");
        }
    </script>
</body>
</html>