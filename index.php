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
 * * NOTA: La logica di parsing è invariata e gestisce l'interpretazione dei tag custom.
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
    
    // 2. Parsing Inline (Immagini e Link)
    
    // Parsing Immagini: [image: Descrizione Alt | URL ]
    $pattern_image = '/\[image: (.*?) \| (.*?) \]/';
    $replacement_image = '<figure><img src="$2" alt="$1" style="max-width:100%; height:auto;"><figcaption>$1</figcaption></figure>';
    $parsed_body = preg_replace($pattern_image, $replacement_image, $raw_body);

    // Parsing Link: [link: Testo del Link | URL ]
    $pattern_link = '/\[link: (.*?) \| (.*?) \]/';
    $replacement_link = '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>';
    $parsed_body = preg_replace($pattern_link, $replacement_link, $parsed_body);
    
    // 3. Formattazione Paragrafi
    $paragraphs = explode("\n\n", trim($parsed_body));
    $formatted_paragraphs = [];
    
    foreach ($paragraphs as $p) {
        $trimmed_p = trim($p);
        if (!empty($trimmed_p)) {
            if (substr($trimmed_p, 0, 1) !== '<') {
                $formatted_paragraphs[] = '<p>' . nl2br($trimmed_p) . '</p>';
            } else {
                 $formatted_paragraphs[] = $trimmed_p;
            }
        }
    }
    
    $data['body'] = implode("\n", $formatted_paragraphs);

    return $data;
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
    <style>
        body { font-family: sans-serif; max-width: 800px; margin: 0 auto; line-height: 1.6; background-color: #fcf1f0;  }
        .post-summary { border-bottom: 2px solid #ddd; padding: 20px 0; margin-bottom: 20px; }
        .post-title { color: rgba(0, 0, 0, 1); margin-top: 0; }
        .post-title a { text-decoration: none; color: inherit; }
        .post-title a:hover { text-decoration: underline; }
        .post-body p { margin: 0 0 10px 0; }
        figure { margin: 15px 0; border: 1px solid #eee; padding: 10px; text-align: center; }
        figcaption { font-style: italic; font-size: 0.9em; color: #666; }
        footer { text-align: center; font-size: 0.8em; color: #999; margin: 40px 0 20px 0; }
        a { color: #000;}
        a:hover { color: #666; }
        a:visited { color: #000; }
        
        /* Stili per l'anteprima (solo in modalità elenco) */
        .preview-content { position: relative; }
        .home .preview-content { max-height: 200px; overflow: hidden; } /* Limite di altezza in home */
        .home .preview-content::after {
            content: "";
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 50px;
            background: linear-gradient(to top, white, rgba(255, 255, 255, 0));
        }
        .single-post .read-more { display: none; } /* Nasconde il link "Leggi tutto" nella pagina singola */
        .home-link { margin-bottom: 20px; display: block; }
    </style>
</head>
<body class="<?php echo $is_single_post ? 'single-post' : 'home'; ?>">

    <?php if ($is_single_post): ?>
        
        <a href="index.php" class="home-link">&laquo; Torna all'elenco</a>
        
        <?php if ($current_post): ?>
            <article class="full-post">
                <h1 class="post-title"><?php echo htmlspecialchars($current_post['title']); ?></h1>
                
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
    
        <h1>&lt;dan1&gt;</h1>
        
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

</body>
</html>