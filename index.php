<?php

$gmail_label = getenv("GMAIL_LABEL");
$server = "{imap.gmail.com:993/imap/ssl/novalidate-cert}{$gmail_label}";

$mbox = imap_open($server, getenv('GMAIL_USERNAME'), getenv('GMAIL_PASSWORD'))
    or die(imap_last_error());

$message_count = imap_num_msg($mbox);

$processed = 0;
$notes = [];
foreach (imap_sort($mbox, SORTARRIVAL, 0) as $msg_num) {
    $message = imap_qprint(imap_fetchbody($mbox, $msg_num, 2, FT_PEEK));
    $dom = new DOMDocument();

    if (!$dom->loadHtml($message)) {
        echo "Message {$msg_num} failed to load HTML\n";
    }

    $xpath = new DOMXPath($dom);

    $title = getTextContent($xpath, '//a[contains(@class,"title")]');
    if (!$title) {
        $overview = imap_fetch_overview($mbox, $msg_num)[0];
        echo "Skipping '{$overview->subject}'... no Pocket title found\n";
        $processed++;
        continue;
    }

    if (!array_key_exists($title, $notes)) {
        $overview = imap_fetch_overview($mbox, $msg_num)[0];

        $date = new DateTime("@{$overview->udate}");
        $date->setTimezone(new DateTimeZone('America/Los_Angeles'));

        $url = getUrl($xpath, '//a[contains(@class,"title")]');

        $notes[$title] = [
            'title'         => $title,
            'domain'        => getTextContent($xpath, '//td[contains(@class,"domain")]/a'),
            'url'           => $url,
            'effective_url' => $url ? getEffectiveUrl($url) : null,
            'body'          => [],
            'date'          => $date->format('l, F j, Y, g:i a'),
            'imap_msg_nums' => [],
        ];
    }

    $quote = getTextContent($xpath, '//td[contains(@class,"quote")]');
    $comment = getTextContent($xpath, '//td[contains(@class,"comment")]');

    $body = '';
    if ($quote) {
        $body .= '> ' . implode("\n> ", explode("\n", $quote)). "\n\n";
    }
    if ($comment) {
        $body .= "{$comment}\n\n";
    }
    $notes[$title]['body'][] = $body;
    $notes[$title]['imap_msg_nums'][] = $msg_num;

    $processed++;
    if ($processed % 5 === 0) {
        echo "{$processed} of {$message_count}\n";
    }
}

$notes = array_values($notes);
$note_count = count($notes);
file_put_contents('results.sh', "");
foreach ($notes as $note_idx => $note) {
    $note_num = $note_idx + 1;
    $note['body'] = trim(implode("", array_reverse($note['body'])));

    $command = "open 'bear://x-callback-url/create?" . http_build_query([
        'title'       => $note['title'],
        'text'        => "## {$note['date']}\n\n{$note['body']}\n\n{$note['effective_url']}",
        'tags'        => "reading/pocket/notes,reading/pocket/notes/{$note['domain']},import/gmail",
        'open_note'   => 'no',
        'new_window'  => 'no',
        'show_window' => 'no',
        'x-error'     => 'https://requestbin.fullcontact.com/17qd1ej1?inspect',
    ], '', '&', PHP_QUERY_RFC3986) . "'\n";
    if ($note_num % 1 === 0) {
        $command .= "echo -n \"\r{$note_num} of {$note_count}\"\n";
    }

    file_put_contents('results.sh', $command, FILE_APPEND);
    imap_mail_move($mbox, implode(',', $note['imap_msg_nums']), "{$gmail_label}/Processed");
}
file_put_contents('results.sh', 'echo ""', FILE_APPEND);

imap_close($mbox);

function getTextContent(DOMXPath $xpath, $expression)
{
    $node_list = $xpath->query($expression);

    if ($node_list->length === 0) {
        return null;
    }

    $text = '';
    foreach ($node_list as $node) {
        $text .= $node->textContent . "\n\n";
    }

    return trim($text, " \t\n\r\0\x0B\xc2\xa0");
}

function getUrl(DOMXPath $xpath, $expression)
{
    foreach($xpath->query($expression) as $title) {
        $url = $title->attributes->getNamedItem('href')->nodeValue;
        if (trim($url)) {
            return trim($url);
        }
    }

    return null;
}

function getEffectiveUrl($url)
{
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    $effective_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);

    curl_close($ch);

    return $effective_url;
}
