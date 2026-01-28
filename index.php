<?php
// ====================================================================
//  Microcoso: sistema CMS minimale in PHP basato su file di testo
//  Realizzato da Daniele Florio con il supporto di AI
//  Why not?
// ====================================================================
$content_dir = 'content/';
$file_extension = '.txt';

// Sanitizzazione dello slug: Rimuove caratteri pericolosi per prevenire Directory Traversal
$requested_post_slug = isset($_GET['post']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['post']) : null;

$posts = [];
$is_single_post = false;
$current_post = null;

/**
 * Interpreta il contenuto di un singolo file testuale.
 * Traduce i tag [key]=value in metadati e i tag custom [image:..] e [link:..] nel corpo.
 * Supporta: bold, italic, headings, lists, code blocks.
 */
function parse_content_file($filepath) {
    // 1. Lettura del file
    $content = file($filepath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    $data = [
        'title' => 'Titolo Sconosciuto',
        'date' => '9999-12-31', // Data alta per ordinamento se manca
        'author' => 'Autore Sconosciuto',
        'body' => ''
    ];
    
    $body_lines = [];
    
    foreach ($content as $line) {
        // Parsing dei Metadati di Intestazione
        if (preg_match('/^\[(title|date|author)\]=(.*)$/i', $line, $matches)) {
            $key = strtolower($matches[1]);
            $value = trim($matches[2]);
            $data[$key] = $value;
        } else {
            $body_lines[] = $line;
        }
    }
    
    $raw_body = implode("\n", $body_lines);
    
    // 2. Parse code blocks first (to avoid parsing their content)
    $code_blocks = [];
    $code_block_count = 0;
    $raw_body = preg_replace_callback('/```([^`]*?)```/s', function($matches) use (&$code_blocks, &$code_block_count) {
        $placeholder = "{{CODE_BLOCK_" . ($code_block_count++) . "}}";
        $code_blocks[$placeholder] = '<pre><code>' . htmlspecialchars($matches[1]) . '</code></pre>';
        return $placeholder;
    }, $raw_body);
    
    // Inline code blocks
    $raw_body = preg_replace_callback('/`([^`]+?)`/', function($matches) {
        return '<code>' . htmlspecialchars($matches[1]) . '</code>';
    }, $raw_body);
    
    // 3. Parsing Immagini e Link (prima di altri elementi inline)
    
    // Parsing Immagini: [image: Descrizione Alt | URL ]
    $pattern_image = '/\[image: (.*?) \| (.*?) \]/';
    $replacement_image = '<figure><img src="$2" alt="$1" style="max-width:100%; height:auto;"><figcaption>$1</figcaption></figure>';
    $parsed_body = preg_replace($pattern_image, $replacement_image, $raw_body);

    // Parsing Link: [link: Testo del Link | URL ]
    $pattern_link = '/\[link: (.*?) \| (.*?) \]/';
    $replacement_link = '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>';
    $parsed_body = preg_replace($pattern_link, $replacement_link, $parsed_body);
    
    // 4. Process line by line for block elements (headings, lists, etc.)
    $lines = explode("\n", trim($parsed_body));
    $formatted_paragraphs = [];
    $current_paragraph = [];
    $current_list = null;
    $current_list_type = null;
    
    for ($i = 0; $i < count($lines); $i++) {
        $line = $lines[$i];
        $trimmed_line = trim($line);
        
        // Check for headings
        if (preg_match('/^(#{2,6})\s+(.+)$/', $trimmed_line, $matches)) {
            // Flush any pending paragraph
            if (!empty($current_paragraph)) {
                $formatted_paragraphs[] = '<p>' . process_inline_formatting(implode("\n", $current_paragraph)) . '</p>';
                $current_paragraph = [];
            }
            // Flush any pending list
            if ($current_list !== null) {
                $formatted_paragraphs[] = $current_list_type === 'ul' ? '<ul>' . implode('', $current_list) . '</ul>' : '<ol>' . implode('', $current_list) . '</ol>';
                $current_list = null;
                $current_list_type = null;
            }
            
            $level = strlen($matches[1]);
            $heading_text = $matches[2];
            $formatted_paragraphs[] = "<h{$level}>" . process_inline_formatting($heading_text) . "</h{$level}>";
        }
        // Check for unordered list items
        elseif (preg_match('/^[-*]\s+(.+)$/', $trimmed_line, $matches)) {
            // Flush any pending paragraph
            if (!empty($current_paragraph)) {
                $formatted_paragraphs[] = '<p>' . process_inline_formatting(implode("\n", $current_paragraph)) . '</p>';
                $current_paragraph = [];
            }
            
            // Flush ordered list if switching types
            if ($current_list_type === 'ol') {
                $formatted_paragraphs[] = '<ol>' . implode('', $current_list) . '</ol>';
                $current_list = null;
                $current_list_type = null;
            }
            
            if ($current_list_type === null) {
                $current_list_type = 'ul';
                $current_list = [];
            }
            
            $current_list[] = '<li>' . process_inline_formatting($matches[1]) . '</li>';
        }
        // Check for ordered list items
        elseif (preg_match('/^\d+\.\s+(.+)$/', $trimmed_line, $matches)) {
            // Flush any pending paragraph
            if (!empty($current_paragraph)) {
                $formatted_paragraphs[] = '<p>' . process_inline_formatting(implode("\n", $current_paragraph)) . '</p>';
                $current_paragraph = [];
            }
            
            // Flush unordered list if switching types
            if ($current_list_type === 'ul') {
                $formatted_paragraphs[] = '<ul>' . implode('', $current_list) . '</ul>';
                $current_list = null;
                $current_list_type = null;
            }
            
            if ($current_list_type === null) {
                $current_list_type = 'ol';
                $current_list = [];
            }
            
            $current_list[] = '<li>' . process_inline_formatting($matches[1]) . '</li>';
        }
        // Check for pre-existing HTML blocks (images, code blocks, etc.)
        elseif (substr($trimmed_line, 0, 1) === '<') {
            // Flush any pending paragraph
            if (!empty($current_paragraph)) {
                $formatted_paragraphs[] = '<p>' . process_inline_formatting(implode("\n", $current_paragraph)) . '</p>';
                $current_paragraph = [];
            }
            // Flush any pending list
            if ($current_list !== null) {
                $formatted_paragraphs[] = $current_list_type === 'ul' ? '<ul>' . implode('', $current_list) . '</ul>' : '<ol>' . implode('', $current_list) . '</ol>';
                $current_list = null;
                $current_list_type = null;
            }
            
            $formatted_paragraphs[] = $trimmed_line;
        }
        // Handle blank lines (paragraph separators)
        elseif (empty($trimmed_line)) {
            if (!empty($current_paragraph)) {
                $formatted_paragraphs[] = '<p>' . process_inline_formatting(implode("\n", $current_paragraph)) . '</p>';
                $current_paragraph = [];
            }
            if ($current_list !== null) {
                $formatted_paragraphs[] = $current_list_type === 'ul' ? '<ul>' . implode('', $current_list) . '</ul>' : '<ol>' . implode('', $current_list) . '</ol>';
                $current_list = null;
                $current_list_type = null;
            }
        }
        // Regular text
        else {
            // Flush any pending list
            if ($current_list !== null) {
                $formatted_paragraphs[] = $current_list_type === 'ul' ? '<ul>' . implode('', $current_list) . '</ul>' : '<ol>' . implode('', $current_list) . '</ol>';
                $current_list = null;
                $current_list_type = null;
            }
            
            $current_paragraph[] = $trimmed_line;
        }
    }
    
    // Flush any remaining paragraph
    if (!empty($current_paragraph)) {
        $formatted_paragraphs[] = '<p>' . process_inline_formatting(nl2br(implode("\n", $current_paragraph))) . '</p>';
    }
    
    // Flush any remaining list
    if ($current_list !== null) {
        $formatted_paragraphs[] = $current_list_type === 'ul' ? '<ul>' . implode('', $current_list) . '</ul>' : '<ol>' . implode('', $current_list) . '</ol>';
    }
    
    $data['body'] = implode("\n", $formatted_paragraphs);
    
    // 5. Restore code blocks
    foreach ($code_blocks as $placeholder => $code_html) {
        $data['body'] = str_replace($placeholder, $code_html, $data['body']);
    }

    return $data;
}

/**
 * Process inline formatting: bold, italic
 */
function process_inline_formatting($text) {
    // Bold: **text**
    $text = preg_replace('/\*\*([^\*]+?)\*\*/', '<strong>$1</strong>', $text);
    
    // Italic: *text* (but not **bold**)
    $text = preg_replace('/(?<!\*)\*([^\*]+?)\*(?!\*)/', '<em>$1</em>', $text);
    
    return $text;
}

// ----------------------------------------------------
// B. Logica di Routing e Caricamento dei File
// ----------------------------------------------------

if ($requested_post_slug) {
    // Modalità Post Singolo
    $filepath = $content_dir . $requested_post_slug . $file_extension;
    
    if (file_exists($filepath)) {
        $current_post = parse_content_file($filepath);
        $current_post['slug'] = $requested_post_slug; // Lo slug è già sanitizzato
        $is_single_post = true;
    }
} else {
    // Modalità Elenco (Home Page)
    if (is_dir($content_dir) && $handle = opendir($content_dir)) {
        while (false !== ($file = readdir($handle))) {
            if ($file != "." && $file != ".." && substr($file, -strlen($file_extension)) === $file_extension) {
                $filepath = $content_dir . $file;
                $data = parse_content_file($filepath);
                $data['slug'] = basename($file, $file_extension); 
                $posts[] = $data;
            }
        }
        closedir($handle);
    }
    
    // Ottimizzazione: Ordinamento per data (dal più recente al più vecchio)
    if (!empty($posts)) {
        usort($posts, function($a, $b) {
            // Confronta i timestamp delle date
            $time_a = strtotime($a['date']);
            $time_b = strtotime($b['date']);
            
            // Ordina in modo decrescente (b - a)
            return $time_b - $time_a;
        });
    }
}

// ====================================================================
// C. Generazione della Pagina HTML (Visualizzazione)
// ====================================================================
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>
        <?php 
            // Titolo dinamico per SEO e usabilità
            echo $is_single_post && $current_post ? htmlspecialchars($current_post['title']) : 'dan1 blog'; 
        ?>
    </title>
    <link id="theme-style" rel="stylesheet" href="css/light.css">
    <style>
        .theme-toggle { position: fixed; top: 12px; right: 12px; z-index: 999; }
        .theme-toggle button { padding: 6px 10px; border-radius: 4px; border: none; cursor: pointer; }
    </style>
</head>
<body class="<?php echo $is_single_post ? 'single-post' : 'home'; ?>">

    <header class="site-header">
        <div class="header-inner">
            <?php if ($is_single_post && $current_post): ?>
                <h1 class="page-title"><?php echo htmlspecialchars($current_post['title']); ?></h1>
            <?php else: ?>
                <h1 class="page-title">&lt;dan1&gt;</h1>
            <?php endif; ?>

            <div class="header-controls">
                <button id="theme-toggle-btn" class="theme-toggle-btn" aria-label="Switch theme" title="Switch theme" aria-pressed="false">
                    <svg class="icon icon-moon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"></path></svg>
                    <svg class="icon icon-sun" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><circle cx="12" cy="12" r="4"></circle><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41" stroke="none"/></svg>
                </button>
            </div>
        </div>
    </header>

    <?php if ($is_single_post): ?>
        
        <a href="index.php" class="home-link">&laquo; Torna all'elenco</a>
        
        <?php if ($current_post): ?>
            <article class="full-post">
                <h2 class="post-title"><?php echo htmlspecialchars($current_post['title']); ?></h2>
                
                <p class="metadata">
                    Pubblicato il: <strong><?php echo htmlspecialchars($current_post['date']); ?></strong> 
                    da <em><?php echo htmlspecialchars($current_post['author']); ?></em>
                </p>
                
                <div class="post-body">
                    <div class="preview-content">
                        <?php 
                            // Stampa il corpo completo e interpretato (HTML sicuro proveniente da file locali)
                            echo $current_post['body']; 
                        ?>
                    </div>
                </div>
            </article>
        <?php else: ?>
            <h2>Errore 404: Post non trovato</h2>
            <p>Il post richiesto non è stato trovato o il nome del file non è valido.</p>
        <?php endif; ?>

    <?php else: ?>
    
        <!-- site title moved to header -->
        
        <?php if (empty($posts)): ?>
            <p>Nessun file di contenuto trovato. Crea i tuoi file .txt nella cartella '<?php echo $content_dir; ?>'.</p>
        <?php else: ?>
            
            <?php foreach ($posts as $post): ?>
                <article class="post-summary">
                    <h1 class="post-title">
                        <a href="?post=<?php echo htmlspecialchars($post['slug']); ?>"><?php echo htmlspecialchars($post['title']); ?></a>
                    </h1>
                    
                    <p class="metadata">
                        Pubblicato il: <strong><?php echo htmlspecialchars($post['date']); ?></strong> 
                        da <em><?php echo htmlspecialchars($post['author']); ?></em>
                    </p>
                    
                    <div class="post-body">
                        <div class="preview-content">
                            <?php echo $post['body']; ?>
                        </div>
                        <a href="?post=<?php echo htmlspecialchars($post['slug']); ?>" class="read-more">Leggi tutto...</a>
                    </div>
                </article>
            <?php endforeach; ?>
            
        <?php endif; ?>

    <?php endif; ?>
    
    <footer>
        <div class="footer-content">
            <p>&copy; <?php echo date("Y"); ?> microcoso - cms self made by Daniele Florio</p>
            <p>Sviluppato in PHP.</p>
        </div>
    </footer>

<script>
(function(){
    var themeLink = document.getElementById('theme-style');
    var toggleBtn = document.getElementById('theme-toggle-btn');
    var LIGHT = 'css/light.css';
    var DARK = 'css/dark.css';

    function applyTheme(name){
        if(!themeLink) return;
        var href = (name === 'dark') ? DARK : LIGHT;
        themeLink.setAttribute('href', href);
        try { localStorage.setItem('theme', name); } catch(e){}
        // expose the current theme to CSS via data-theme on <html>
        document.documentElement.setAttribute('data-theme', name);
        if(toggleBtn) toggleBtn.setAttribute('aria-pressed', name === 'dark' ? 'true' : 'false');
    }

    var stored = null;
    try { stored = localStorage.getItem('theme'); } catch(e){}
    var initial = (stored === 'dark') ? 'dark' : 'light';
    applyTheme(initial);

    if(toggleBtn){
        toggleBtn.addEventListener('click', function(){
            var current = (localStorage.getItem('theme') === 'dark') ? 'dark' : 'light';
            var next = (current === 'dark') ? 'light' : 'dark';
            applyTheme(next);
        });
    }
})();
</script>

</body>
</html>