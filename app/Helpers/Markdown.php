<?php

declare(strict_types=1);

/**
 * NOTICE OF LICENSE.
 *
 * UNIT3D Community Edition is open-sourced software licensed under the GNU Affero General Public License v3.0
 * The details is bundled with this project in the file LICENSE.txt.
 *
 * @project    UNIT3D Community Edition
 *
 * @author     HDVinnie <hdinnovations@protonmail.com>
 * @license    https://www.gnu.org/licenses/agpl-3.0.en.html/ GNU Affero General Public License v3.0
 * @credits    Emanuil Rusev <https://erusev.com>
 */

namespace App\Helpers;

class Markdown
{
    public function text($text)
    {
        $Elements = $this->textElements($text);

        // convert to markup
        $markup = $this->elements($Elements);

        // trim line breaks
        return trim((string) $markup, "\n");
    }

    protected function textElements($text)
    {
        // make sure no definitions are set
        $this->DefinitionData = [];

        // standardize line breaks
        $text = str_replace(["\r\n", "\r"], "\n", (string) $text);

        // remove surrounding line breaks
        $text = trim($text, "\n");

        // split text into lines
        $lines = explode("\n", $text);

        // iterate through lines to identify blocks
        return $this->linesElements($lines);
    }

    //
    // Setters
    //

    public function setBreaksEnabled($breaksEnabled)
    {
        $this->breaksEnabled = $breaksEnabled;

        return $this;
    }

    protected $breaksEnabled;

    public function setMarkupEscaped($markupEscaped)
    {
        $this->markupEscaped = $markupEscaped;

        return $this;
    }

    protected $markupEscaped;

    public function setUrlsLinked($urlsLinked)
    {
        $this->urlsLinked = $urlsLinked;

        return $this;
    }

    protected $urlsLinked = true;

    public function setSafeMode($safeMode)
    {
        $this->safeMode = (bool) $safeMode;

        return $this;
    }

    protected $safeMode;

    public function setStrictMode($strictMode)
    {
        $this->strictMode = (bool) $strictMode;

        return $this;
    }

    protected $strictMode;

    protected $safeLinksWhitelist = [
        'http://',
        'https://',
        'ftp://',
        'ftps://',
        'mailto:',
        'tel:',
        'data:image/png;base64,',
        'data:image/gif;base64,',
        'data:image/jpeg;base64,',
        'irc:',
        'ircs:',
        'git:',
        'ssh:',
        'news:',
        'steam:',
    ];

    //
    // Lines
    //

    protected $BlockTypes = [
        '#' => ['Header'],
        '*' => ['Rule', 'List'],
        '+' => ['List'],
        '-' => ['SetextHeader', 'Table', 'Rule', 'List'],
        '0' => ['List'],
        '1' => ['List'],
        '2' => ['List'],
        '3' => ['List'],
        '4' => ['List'],
        '5' => ['List'],
        '6' => ['List'],
        '7' => ['List'],
        '8' => ['List'],
        '9' => ['List'],
        ':' => ['Table'],
        '<' => ['Comment', 'Markup'],
        '=' => ['SetextHeader'],
        '>' => ['Quote'],
        '[' => ['Reference'],
        '_' => ['Rule'],
        '`' => ['FencedCode'],
        '|' => ['Table'],
        '~' => ['FencedCode'],
    ];

    // ~

    protected $unmarkedBlockTypes = [
        'Code',
    ];

    //
    // Blocks
    //

    protected function lines(array $lines)
    {
        return $this->elements($this->linesElements($lines));
    }

    protected function linesElements(array $lines)
    {
        $Elements = [];
        $CurrentBlock = null;

        foreach ($lines as $line) {
            if (rtrim((string) $line) === '') {
                if (isset($CurrentBlock)) {
                    $CurrentBlock['interrupted'] = (
                        isset($CurrentBlock['interrupted'])
                        ? $CurrentBlock['interrupted'] + 1 : 1
                    );
                }

                continue;
            }

            while (($beforeTab = strstr((string) $line, "\t", true)) !== false) {
                $shortage = 4 - mb_strlen($beforeTab, 'utf-8') % 4;

                $line = $beforeTab
                    .str_repeat(' ', $shortage)
                    .substr((string) $line, \strlen($beforeTab) + 1);
            }

            $indent = strspn((string) $line, ' ');

            $text = $indent > 0 ? substr((string) $line, $indent) : $line;

            // ~

            $Line = ['body' => $line, 'indent' => $indent, 'text' => $text];

            // ~

            if (isset($CurrentBlock['continuable'])) {
                $methodName = 'block'.$CurrentBlock['type'].'Continue';
                $Block = $this->$methodName($Line, $CurrentBlock);

                if (isset($Block)) {
                    $CurrentBlock = $Block;

                    continue;
                }

                if ($this->isBlockCompletable($CurrentBlock['type'])) {
                    $methodName = 'block'.$CurrentBlock['type'].'Complete';
                    $CurrentBlock = $this->$methodName($CurrentBlock);
                }
            }

            // ~

            $marker = $text[0];

            // ~

            $blockTypes = $this->unmarkedBlockTypes;

            if (isset($this->BlockTypes[$marker])) {
                foreach ($this->BlockTypes[$marker] as $blockType) {
                    $blockTypes[] = $blockType;
                }
            }

            //
            // ~

            foreach ($blockTypes as $blockType) {
                $Block = $this->{\sprintf('block%s', $blockType)}($Line, $CurrentBlock);

                if (isset($Block)) {
                    $Block['type'] = $blockType;

                    if (!isset($Block['identified'])) {
                        if (isset($CurrentBlock)) {
                            $Elements[] = $this->extractElement($CurrentBlock);
                        }

                        $Block['identified'] = true;
                    }

                    if ($this->isBlockContinuable($blockType)) {
                        $Block['continuable'] = true;
                    }

                    $CurrentBlock = $Block;

                    continue 2;
                }
            }

            // ~

            if (isset($CurrentBlock) && $CurrentBlock['type'] === 'Paragraph') {
                $Block = $this->paragraphContinue($Line, $CurrentBlock);
            }

            if (isset($Block)) {
                $CurrentBlock = $Block;
            } else {
                if (isset($CurrentBlock)) {
                    $Elements[] = $this->extractElement($CurrentBlock);
                }

                $CurrentBlock = $this->paragraph($Line);

                $CurrentBlock['identified'] = true;
            }
        }

        // ~

        if (isset($CurrentBlock['continuable']) && $this->isBlockCompletable($CurrentBlock['type'])) {
            $methodName = 'block'.$CurrentBlock['type'].'Complete';
            $CurrentBlock = $this->$methodName($CurrentBlock);
        }

        // ~

        if (isset($CurrentBlock)) {
            $Elements[] = $this->extractElement($CurrentBlock);
        }

        // ~

        return $Elements;
    }

    protected function extractElement(array $Component)
    {
        if (!isset($Component['element'])) {
            if (isset($Component['markup'])) {
                $Component['element'] = ['rawHtml' => $Component['markup']];
            } elseif (isset($Component['hidden'])) {
                $Component['element'] = [];
            }
        }

        return $Component['element'];
    }

    protected function isBlockContinuable($Type)
    {
        return method_exists($this, 'block'.$Type.'Continue');
    }

    protected function isBlockCompletable($Type)
    {
        return method_exists($this, 'block'.$Type.'Complete');
    }

    //
    // Code

    protected function blockCode($Line, $Block = null)
    {
        if (isset($Block) && $Block['type'] === 'Paragraph' && !isset($Block['interrupted'])) {
            return;
        }

        if ($Line['indent'] >= 4) {
            $text = substr((string) $Line['body'], 4);

            return [
                'element' => [
                    'name'    => 'pre',
                    'element' => [
                        'name' => 'code',
                        'text' => $text,
                    ],
                ],
            ];
        }
    }

    protected function blockCodeContinue($Line, $Block)
    {
        if ($Line['indent'] >= 4) {
            if (isset($Block['interrupted'])) {
                $Block['element']['element']['text'] .= str_repeat("\n", $Block['interrupted']);

                unset($Block['interrupted']);
            }

            $Block['element']['element']['text'] .= "\n";

            $text = substr((string) $Line['body'], 4);

            $Block['element']['element']['text'] .= $text;

            return $Block;
        }
    }

    protected function blockCodeComplete($Block)
    {
        return $Block;
    }

    //
    // Comment

    protected function blockComment($Line)
    {
        if ($this->markupEscaped || $this->safeMode) {
            return;
        }

        if (str_starts_with((string) $Line['text'], '<!--')) {
            $Block = [
                'element' => [
                    'rawHtml'   => $Line['body'],
                    'autobreak' => true,
                ],
            ];

            if (str_contains((string) $Line['text'], '-->')) {
                $Block['closed'] = true;
            }

            return $Block;
        }
    }

    protected function blockCommentContinue($Line, array $Block)
    {
        if (isset($Block['closed'])) {
            return;
        }

        $Block['element']['rawHtml'] .= "\n".$Line['body'];

        if (str_contains((string) $Line['text'], '-->')) {
            $Block['closed'] = true;
        }

        return $Block;
    }

    //
    // Fenced Code

    protected function blockFencedCode($Line)
    {
        $marker = $Line['text'][0];

        $openerLength = strspn((string) $Line['text'], (string) $marker);

        if ($openerLength < 3) {
            return;
        }

        $infostring = trim(substr((string) $Line['text'], $openerLength), "\t ");

        if (str_contains($infostring, '`')) {
            return;
        }

        $Element = [
            'name' => 'code',
            'text' => '',
        ];

        if ($infostring !== '') {
            /**
             * https://www.w3.org/TR/2011/WD-html5-20110525/elements.html#classes
             * Every HTML element may have a class attribute specified.
             * The attribute, if specified, must have a value that is a set
             * of space-separated tokens representing the various classes
             * that the element belongs to.
             * [...]
             * The space characters, for the purposes of this specification,
             * are U+0020 SPACE, U+0009 CHARACTER TABULATION (tab),
             * U+000A LINE FEED (LF), U+000C FORM FEED (FF), and
             * U+000D CARRIAGE RETURN (CR).
             */
            $language = substr($infostring, 0, strcspn($infostring, " \t\n\f\r"));

            $Element['attributes'] = ['class' => \sprintf('language-%s', $language)];
        }

        return [
            'char'         => $marker,
            'openerLength' => $openerLength,
            'element'      => [
                'name'    => 'pre',
                'element' => $Element,
            ],
        ];
    }

    protected function blockFencedCodeContinue($Line, $Block)
    {
        if (isset($Block['complete'])) {
            return;
        }

        if (isset($Block['interrupted'])) {
            $Block['element']['element']['text'] .= str_repeat("\n", $Block['interrupted']);

            unset($Block['interrupted']);
        }

        if (($len = strspn((string) $Line['text'], (string) $Block['char'])) >= $Block['openerLength'] && rtrim(substr((string) $Line['text'], $len), ' ') === ''
        ) {
            $Block['element']['element']['text'] = substr((string) $Block['element']['element']['text'], 1);

            $Block['complete'] = true;

            return $Block;
        }

        $Block['element']['element']['text'] .= "\n".$Line['body'];

        return $Block;
    }

    protected function blockFencedCodeComplete($Block)
    {
        return $Block;
    }

    //
    // Header

    protected function blockHeader($Line)
    {
        $level = strspn((string) $Line['text'], '#');

        if ($level > 6) {
            return;
        }

        $text = trim((string) $Line['text'], '#');

        if ($this->strictMode && isset($text[0]) && $text[0] !== ' ') {
            return;
        }

        $text = trim($text, ' ');

        return [
            'element' => [
                'name'    => 'h'.$level,
                'handler' => [
                    'function'    => 'lineElements',
                    'argument'    => $text,
                    'destination' => 'elements',
                ],
            ],
        ];
    }

    //
    // List

    protected function blockList($Line, ?array $CurrentBlock = null)
    {
        [$name, $pattern] = $Line['text'][0] <= '-' ? ['ul', '[*+-]'] : ['ol', '[0-9]{1,9}+[.\)]'];

        if (preg_match('/^('.$pattern.'([ ]++|$))(.*+)/', (string) $Line['text'], $matches)) {
            $contentIndent = \strlen((string) $matches[2]);

            if ($contentIndent >= 5) {
                $contentIndent--;
                $matches[1] = substr($matches[1], 0, -$contentIndent);
                $matches[3] = str_repeat(' ', $contentIndent).$matches[3];
            } elseif ($contentIndent === 0) {
                $matches[1] .= ' ';
            }

            $markerWithoutWhitespace = strstr($matches[1], ' ', true);

            $Block = [
                'indent'  => $Line['indent'],
                'pattern' => $pattern,
                'data'    => [
                    'type'       => $name,
                    'marker'     => $matches[1],
                    'markerType' => ($name === 'ul' ? $markerWithoutWhitespace : substr($markerWithoutWhitespace, -1)),
                ],
                'element' => [
                    'name'     => $name,
                    'elements' => [],
                ],
            ];
            $Block['data']['markerTypeRegex'] = preg_quote($Block['data']['markerType'], '/');

            if ($name === 'ol') {
                $listStart = ltrim(strstr($matches[1], (string) $Block['data']['markerType'], true), '0') ?: '0';

                if ($listStart !== '1') {
                    if (
                        isset($CurrentBlock) && $CurrentBlock['type'] === 'Paragraph' && !isset($CurrentBlock['interrupted'])
                    ) {
                        return;
                    }

                    $Block['element']['attributes'] = ['start' => $listStart];
                }
            }

            $Block['li'] = [
                'name'    => 'li',
                'handler' => [
                    'function'    => 'li',
                    'argument'    => empty($matches[3]) ? [] : [$matches[3]],
                    'destination' => 'elements',
                ],
            ];

            $Block['element']['elements'][] = &$Block['li'];

            return $Block;
        }
    }

    protected function blockListContinue($Line, array $Block)
    {
        $matches = [];

        if (isset($Block['interrupted']) && empty($Block['li']['handler']['argument'])) {
            return;
        }

        $requiredIndent = ($Block['indent'] + \strlen((string) $Block['data']['marker']));

        if ($Line['indent'] < $requiredIndent && ($Block['data']['type'] === 'ol' && preg_match('/^[0-9]++'.$Block['data']['markerTypeRegex'].'(?:[ ]++(.*)|$)/', (string) $Line['text'], $matches) || $Block['data']['type'] === 'ul' && preg_match('/^'.$Block['data']['markerTypeRegex'].'(?:[ ]++(.*)|$)/', (string) $Line['text'], $matches))) {
            if (isset($Block['interrupted'])) {
                $Block['li']['handler']['argument'][] = '';

                $Block['loose'] = true;

                unset($Block['interrupted']);
            }

            unset($Block['li']);

            $text = $matches[1] ?? '';

            $Block['indent'] = $Line['indent'];

            $Block['li'] = [
                'name'    => 'li',
                'handler' => [
                    'function'    => 'li',
                    'argument'    => [$text],
                    'destination' => 'elements',
                ],
            ];

            $Block['element']['elements'][] = &$Block['li'];

            return $Block;
        }

        if ($Line['indent'] < $requiredIndent && $this->blockList($Line)
        ) {
            return;
        }

        if ($Line['text'][0] === '[' && $this->blockReference($Line)) {
            return $Block;
        }

        if ($Line['indent'] >= $requiredIndent) {
            if (isset($Block['interrupted'])) {
                $Block['li']['handler']['argument'][] = '';

                $Block['loose'] = true;

                unset($Block['interrupted']);
            }

            $text = substr((string) $Line['body'], $requiredIndent);

            $Block['li']['handler']['argument'][] = $text;

            return $Block;
        }

        if (!isset($Block['interrupted'])) {
            $text = preg_replace('/^[ ]{0,'.$requiredIndent.'}+/', '', (string) $Line['body']);

            $Block['li']['handler']['argument'][] = $text;

            return $Block;
        }
    }

    protected function blockListComplete(array $Block)
    {
        if (isset($Block['loose'])) {
            foreach ($Block['element']['elements'] as &$li) {
                if (end($li['handler']['argument']) !== '') {
                    $li['handler']['argument'][] = '';
                }
            }
        }

        return $Block;
    }

    //
    // Quote

    protected function blockQuote($Line)
    {
        if (preg_match('#^>[ ]?+(.*+)#', (string) $Line['text'], $matches)) {
            return [
                'element' => [
                    'name'    => 'blockquote',
                    'handler' => [
                        'function'    => 'linesElements',
                        'argument'    => (array) $matches[1],
                        'destination' => 'elements',
                    ],
                ],
            ];
        }
    }

    protected function blockQuoteContinue($Line, array $Block)
    {
        if (isset($Block['interrupted'])) {
            return;
        }

        if ($Line['text'][0] === '>' && preg_match('#^>[ ]?+(.*+)#', (string) $Line['text'], $matches)) {
            $Block['element']['handler']['argument'][] = $matches[1];

            return $Block;
        }

        if (!isset($Block['interrupted'])) {
            $Block['element']['handler']['argument'][] = $Line['text'];

            return $Block;
        }
    }

    //
    // Rule

    protected function blockRule($Line)
    {
        $marker = $Line['text'][0];

        if (substr_count((string) $Line['text'], (string) $marker) >= 3 && rtrim((string) $Line['text'], \sprintf(' %s', $marker)) === '') {
            return [
                'element' => [
                    'name' => 'hr',
                ],
            ];
        }
    }

    //
    // Setext

    protected function blockSetextHeader($Line, ?array $Block = null)
    {
        if (!isset($Block) || $Block['type'] !== 'Paragraph' || isset($Block['interrupted'])) {
            return;
        }

        if ($Line['indent'] < 4 && rtrim(rtrim((string) $Line['text'], ' '), $Line['text'][0]) === '') {
            $Block['element']['name'] = $Line['text'][0] === '=' ? 'h1' : 'h2';

            return $Block;
        }
    }

    //
    // Markup

    protected function blockMarkup($Line)
    {
        if ($this->markupEscaped || $this->safeMode) {
            return;
        }

        if (preg_match('/^<[\/]?+(\w*)(?:[ ]*+'.$this->regexHtmlAttribute.')*+[ ]*+(\/)?>/', (string) $Line['text'], $matches)) {
            $element = strtolower($matches[1]);

            if (\in_array($element, $this->textLevelElements)) {
                return;
            }

            return [
                'name'    => $matches[1],
                'element' => [
                    'rawHtml'   => $Line['text'],
                    'autobreak' => true,
                ],
            ];
        }
    }

    protected function blockMarkupContinue($Line, array $Block)
    {
        if (isset($Block['closed']) || isset($Block['interrupted'])) {
            return;
        }

        $Block['element']['rawHtml'] .= "\n".$Line['body'];

        return $Block;
    }

    //
    // Reference

    protected function blockReference($Line)
    {
        if (str_contains((string) $Line['text'], ']') && preg_match('#^\[(.+?)\]:[ ]*+<?(\S+?)>?(?:[ ]+["\'(](.+)["\')])?[ ]*+$#', (string) $Line['text'], $matches)
        ) {
            $id = strtolower($matches[1]);

            $Data = [
                'url'   => $matches[2],
                'title' => $matches[3] ?? null,
            ];

            $this->DefinitionData['Reference'][$id] = $Data;

            return [
                'element' => [],
            ];
        }
    }

    //
    // Table

    protected function blockTable($Line, ?array $Block = null)
    {
        if (!isset($Block) || $Block['type'] !== 'Paragraph' || isset($Block['interrupted'])) {
            return;
        }

        if (
            (!str_contains((string) $Block['element']['handler']['argument'], '|') && !str_contains((string) $Line['text'], '|') && !str_contains(
                (string) $Line['text'],
                ':'
            )) || str_contains((string) $Block['element']['handler']['argument'], "\n")
        ) {
            return;
        }

        if (rtrim((string) $Line['text'], ' -:|') !== '') {
            return;
        }

        $alignments = [];

        $divider = $Line['text'];

        $divider = trim((string) $divider);
        $divider = trim($divider, '|');

        $dividerCells = explode('|', $divider);

        foreach ($dividerCells as $dividerCell) {
            $dividerCell = trim($dividerCell);

            if ($dividerCell === '') {
                return;
            }

            $alignment = null;

            if ($dividerCell[0] === ':') {
                $alignment = 'left';
            }

            if (str_ends_with($dividerCell, ':')) {
                $alignment = $alignment === 'left' ? 'center' : 'right';
            }

            $alignments[] = $alignment;
        }

        // ~

        $HeaderElements = [];

        $header = $Block['element']['handler']['argument'];

        $header = trim((string) $header);
        $header = trim($header, '|');

        $headerCells = explode('|', $header);

        if (\count($headerCells) !== \count($alignments)) {
            return;
        }

        foreach ($headerCells as $index => $headerCell) {
            $headerCell = trim($headerCell);

            $HeaderElement = [
                'name'    => 'th',
                'handler' => [
                    'function'    => 'lineElements',
                    'argument'    => $headerCell,
                    'destination' => 'elements',
                ],
            ];

            if (isset($alignments[$index])) {
                $alignment = $alignments[$index];

                $HeaderElement['attributes'] = [
                    'style' => \sprintf('text-align: %s;', $alignment),
                ];
            }

            $HeaderElements[] = $HeaderElement;
        }

        // ~

        $Block = [
            'alignments' => $alignments,
            'identified' => true,
            'element'    => [
                'name'     => 'table',
                'elements' => [],
            ],
        ];

        $Block['element']['elements'][] = [
            'name' => 'thead',
        ];

        $Block['element']['elements'][] = [
            'name'     => 'tbody',
            'elements' => [],
        ];

        $Block['element']['elements'][0]['elements'][] = [
            'name'     => 'tr',
            'elements' => $HeaderElements,
        ];

        return $Block;
    }

    protected function blockTableContinue($Line, array $Block)
    {
        if (isset($Block['interrupted'])) {
            return;
        }

        if ((is_countable($Block['alignments']) ? \count($Block['alignments']) : 0) === 1 || $Line['text'][0] === '|' || strpos((string) $Line['text'], '|')) {
            $Elements = [];

            $row = $Line['text'];

            $row = trim((string) $row);
            $row = trim($row, '|');

            preg_match_all('#(?:(\\\[|])|[^|`]|`[^`]++`|`)++#', $row, $matches);

            $cells = \array_slice($matches[0], 0, is_countable($Block['alignments']) ? \count($Block['alignments']) : 0);

            foreach ($cells as $index => $cell) {
                $cell = trim((string) $cell);

                $Element = [
                    'name'    => 'td',
                    'handler' => [
                        'function'    => 'lineElements',
                        'argument'    => $cell,
                        'destination' => 'elements',
                    ],
                ];

                if (isset($Block['alignments'][$index])) {
                    $Element['attributes'] = [
                        'style' => 'text-align: '.$Block['alignments'][$index].';',
                    ];
                }

                $Elements[] = $Element;
            }

            $Element = [
                'name'     => 'tr',
                'elements' => $Elements,
            ];

            $Block['element']['elements'][1]['elements'][] = $Element;

            return $Block;
        }
    }

    //
    // ~
    //

    protected function paragraph($Line)
    {
        return [
            'type'    => 'Paragraph',
            'element' => [
                'name'    => 'p',
                'handler' => [
                    'function'    => 'lineElements',
                    'argument'    => $Line['text'],
                    'destination' => 'elements',
                ],
            ],
        ];
    }

    protected function paragraphContinue($Line, array $Block)
    {
        if (isset($Block['interrupted'])) {
            return;
        }

        $Block['element']['handler']['argument'] .= "\n".$Line['text'];

        return $Block;
    }

    //
    // Inline Elements
    //

    protected $InlineTypes = [
        '!'  => ['Image'],
        '&'  => ['SpecialCharacter'],
        '*'  => ['Emphasis'],
        ':'  => ['Url'],
        '<'  => ['UrlTag', 'EmailTag', 'Markup'],
        '['  => ['Link'],
        '_'  => ['Emphasis'],
        '`'  => ['Code'],
        '~'  => ['Strikethrough'],
        '\\' => ['EscapeSequence'],
    ];

    // ~

    protected $inlineMarkerList = '!*_&[:<`~\\';

    //
    // ~
    //

    public function line($text, $nonNestables = [])
    {
        return $this->elements($this->lineElements($text, $nonNestables));
    }

    protected function lineElements($text, $nonNestables = [])
    {
        // standardize line breaks
        $text = str_replace(["\r\n", "\r"], "\n", (string) $text);

        $Elements = [];

        $nonNestables = (
            empty($nonNestables)
            ? []
            : array_combine($nonNestables, $nonNestables)
        );

        // $excerpt is based on the first occurrence of a marker

        while ($excerpt = strpbrk($text, (string) $this->inlineMarkerList)) {
            $marker = $excerpt[0];

            $markerPosition = \strlen((string) $text) - \strlen($excerpt);

            $Excerpt = ['text' => $excerpt, 'context' => $text];

            foreach ($this->InlineTypes[$marker] as $inlineType) {
                // check to see if the current inline type is nestable in the current context

                if (isset($nonNestables[$inlineType])) {
                    continue;
                }

                $Inline = $this->{\sprintf('inline%s', $inlineType)}($Excerpt);

                if (!isset($Inline)) {
                    continue;
                }

                // makes sure that the inline belongs to "our" marker

                if (isset($Inline['position']) && $Inline['position'] > $markerPosition) {
                    continue;
                }

                // sets a default inline position

                if (!isset($Inline['position'])) {
                    $Inline['position'] = $markerPosition;
                }

                // cause the new element to 'inherit' our non nestables

                $Inline['element']['nonNestables'] = isset($Inline['element']['nonNestables'])
                    ? array_merge($Inline['element']['nonNestables'], $nonNestables)
                    : $nonNestables;

                // the text that comes before the inline
                $unmarkedText = substr($text, 0, $Inline['position']);

                // compile the unmarked text
                $InlineText = $this->inlineText($unmarkedText);
                $Elements[] = $InlineText['element'];

                // compile the inline
                $Elements[] = $this->extractElement($Inline);

                // remove the examined text
                $text = substr($text, $Inline['position'] + $Inline['extent']);

                continue 2;
            }

            // the marker does not belong to an inline

            $unmarkedText = substr($text, 0, $markerPosition + 1);

            $InlineText = $this->inlineText($unmarkedText);
            $Elements[] = $InlineText['element'];

            $text = substr($text, $markerPosition + 1);
        }

        $InlineText = $this->inlineText($text);
        $Elements[] = $InlineText['element'];

        foreach ($Elements as &$Element) {
            if (!isset($Element['autobreak'])) {
                $Element['autobreak'] = false;
            }
        }

        return $Elements;
    }

    //
    // ~
    //

    protected function inlineText($text)
    {
        $Inline = [
            'extent'  => \strlen((string) $text),
            'element' => [],
        ];

        $Inline['element']['elements'] = self::pregReplaceElements(
            $this->breaksEnabled ? '/[ ]*+\n/' : '/(?:[ ]*+\\\\|[ ]{2,}+)\n/',
            [
                ['name' => 'br'],
                ['text' => "\n"],
            ],
            $text
        );

        return $Inline;
    }

    protected function inlineCode($Excerpt)
    {
        $marker = $Excerpt['text'][0];

        if (preg_match('/^(['.$marker.']++)[ ]*+(.+?)[ ]*+(?<!['.$marker.'])\1(?!'.$marker.')/s', (string) $Excerpt['text'], $matches)) {
            $text = $matches[2];
            $text = preg_replace('#[ ]*+\n#', ' ', $text);

            return [
                'extent'  => \strlen((string) $matches[0]),
                'element' => [
                    'name' => 'code',
                    'text' => $text,
                ],
            ];
        }
    }

    protected function inlineEmailTag($Excerpt)
    {
        $hostnameLabel = '[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?';

        $commonMarkEmail = '[a-zA-Z0-9.!#$%&\'*+\/=?^_`{|}~-]++@'
            .$hostnameLabel.'(?:\.'.$hostnameLabel.')*';

        if (str_contains((string) $Excerpt['text'], '>') && preg_match(\sprintf('/^<((mailto:)?%s)>/i', $commonMarkEmail), (string) $Excerpt['text'], $matches)
        ) {
            $url = $matches[1];

            if (!isset($matches[2])) {
                $url = \sprintf('mailto:%s', $url);
            }

            return [
                'extent'  => \strlen((string) $matches[0]),
                'element' => [
                    'name'       => 'a',
                    'text'       => $matches[1],
                    'attributes' => [
                        'href' => $url,
                    ],
                ],
            ];
        }
    }

    protected function inlineEmphasis($Excerpt)
    {
        if (!isset($Excerpt['text'][1])) {
            return;
        }

        $marker = $Excerpt['text'][0];

        if ($Excerpt['text'][1] === $marker && preg_match($this->StrongRegex[$marker], (string) $Excerpt['text'], $matches)) {
            $emphasis = 'strong';
        } elseif (preg_match($this->EmRegex[$marker], (string) $Excerpt['text'], $matches)) {
            $emphasis = 'em';
        } else {
            return;
        }

        return [
            'extent'  => \strlen((string) $matches[0]),
            'element' => [
                'name'    => $emphasis,
                'handler' => [
                    'function'    => 'lineElements',
                    'argument'    => $matches[1],
                    'destination' => 'elements',
                ],
            ],
        ];
    }

    protected function inlineEscapeSequence($Excerpt)
    {
        if (isset($Excerpt['text'][1]) && \in_array($Excerpt['text'][1], $this->specialCharacters)) {
            return [
                'element' => ['rawHtml' => $Excerpt['text'][1]],
                'extent'  => 2,
            ];
        }
    }

    protected function inlineImage($Excerpt)
    {
        if (!isset($Excerpt['text'][1]) || $Excerpt['text'][1] !== '[') {
            return;
        }

        $Excerpt['text'] = substr((string) $Excerpt['text'], 1);

        $Link = $this->inlineLink($Excerpt);

        if ($Link === null) {
            return;
        }

        $Inline = [
            'extent'  => $Link['extent'] + 1,
            'element' => [
                'name'       => 'img',
                'attributes' => [
                    'src' => $Link['element']['attributes']['href'],
                    'alt' => $Link['element']['handler']['argument'],
                ],
                'autobreak' => true,
            ],
        ];

        $Inline['element']['attributes'] += $Link['element']['attributes'];

        unset($Inline['element']['attributes']['href']);

        return $Inline;
    }

    protected function inlineLink($Excerpt)
    {
        $Element = [
            'name'    => 'a',
            'handler' => [
                'function'    => 'lineElements',
                'argument'    => null,
                'destination' => 'elements',
            ],
            'nonNestables' => ['Url', 'Link'],
            'attributes'   => [
                'href'  => null,
                'title' => null,
            ],
        ];

        $extent = 0;

        $remainder = $Excerpt['text'];

        if (preg_match('#\[((?:[^][]++|(?R))*+)\]#', (string) $remainder, $matches)) {
            $Element['handler']['argument'] = $matches[1];

            $extent += \strlen((string) $matches[0]);

            $remainder = substr((string) $remainder, $extent);
        } else {
            return;
        }

        if (preg_match('#^[(]\s*+((?:[^ ()]++|[(][^ )]+[)])++)(?:[ ]+("[^"]*+"|\'[^\']*+\'))?\s*+[)]#', $remainder, $matches)) {
            $Element['attributes']['href'] = $matches[1];

            if (isset($matches[2])) {
                $Element['attributes']['title'] = substr($matches[2], 1, -1);
            }

            $extent += \strlen((string) $matches[0]);
        } else {
            if (preg_match('#^\s*\[(.*?)\]#', $remainder, $matches)) {
                $definition = $matches[1] != '' ? $matches[1] : $Element['handler']['argument'];
                $definition = strtolower($definition);

                $extent += \strlen((string) $matches[0]);
            } else {
                $definition = strtolower($Element['handler']['argument']);
            }

            if (!isset($this->DefinitionData['Reference'][$definition])) {
                return;
            }

            $Definition = $this->DefinitionData['Reference'][$definition];

            $Element['attributes']['href'] = $Definition['url'];
            $Element['attributes']['title'] = $Definition['title'];
        }

        return [
            'extent'  => $extent,
            'element' => $Element,
        ];
    }

    protected function inlineMarkup($Excerpt)
    {
        if ($this->markupEscaped || $this->safeMode || !str_contains((string) $Excerpt['text'], '>')) {
            return;
        }

        if ($Excerpt['text'][1] === '/' && preg_match('#^<\/\w[\w-]*+[ ]*+>#s', (string) $Excerpt['text'], $matches)) {
            return [
                'element' => ['rawHtml' => $matches[0]],
                'extent'  => \strlen((string) $matches[0]),
            ];
        }

        if ($Excerpt['text'][1] === '!' && preg_match('#^<!---?[^>-](?:-?+[^-])*-->#s', (string) $Excerpt['text'], $matches)) {
            return [
                'element' => ['rawHtml' => $matches[0]],
                'extent'  => \strlen((string) $matches[0]),
            ];
        }

        if ($Excerpt['text'][1] !== ' ' && preg_match('/^<\w[\w-]*+(?:[ ]*+'.$this->regexHtmlAttribute.')*+[ ]*+\/?>/s', (string) $Excerpt['text'], $matches)) {
            return [
                'element' => ['rawHtml' => $matches[0]],
                'extent'  => \strlen((string) $matches[0]),
            ];
        }
    }

    protected function inlineSpecialCharacter($Excerpt)
    {
        if (substr((string) $Excerpt['text'], 1, 1) !== ' ' && str_contains((string) $Excerpt['text'], ';') && preg_match('/^&(#?+[0-9a-zA-Z]++);/', (string) $Excerpt['text'], $matches)
        ) {
            return [
                'element' => ['rawHtml' => '&'.$matches[1].';'],
                'extent'  => \strlen((string) $matches[0]),
            ];
        }
    }

    protected function inlineStrikethrough($Excerpt)
    {
        if (!isset($Excerpt['text'][1])) {
            return;
        }

        if ($Excerpt['text'][1] === '~' && preg_match('#^~~(?=\S)(.+?)(?<=\S)~~#', (string) $Excerpt['text'], $matches)) {
            return [
                'extent'  => \strlen((string) $matches[0]),
                'element' => [
                    'name'    => 'del',
                    'handler' => [
                        'function'    => 'lineElements',
                        'argument'    => $matches[1],
                        'destination' => 'elements',
                    ],
                ],
            ];
        }
    }

    protected function inlineUrl($Excerpt)
    {
        if ($this->urlsLinked !== true || !isset($Excerpt['text'][2]) || $Excerpt['text'][2] !== '/') {
            return;
        }

        if (str_contains((string) $Excerpt['context'], 'http') && preg_match('#\bhttps?+:[\/]{2}[^\s<]+\b\/*+#ui', (string) $Excerpt['context'], $matches, PREG_OFFSET_CAPTURE)
        ) {
            $url = $matches[0][0];

            return [
                'extent'   => \strlen((string) $matches[0][0]),
                'position' => $matches[0][1],
                'element'  => [
                    'name'       => 'a',
                    'text'       => $url,
                    'attributes' => [
                        'href' => $url,
                    ],
                ],
            ];
        }
    }

    protected function inlineUrlTag($Excerpt)
    {
        if (str_contains((string) $Excerpt['text'], '>') && preg_match('#^<(\w++:\/{2}[^ >]++)>#i', (string) $Excerpt['text'], $matches)) {
            $url = $matches[1];

            return [
                'extent'  => \strlen((string) $matches[0]),
                'element' => [
                    'name'       => 'a',
                    'text'       => $url,
                    'attributes' => [
                        'href' => $url,
                    ],
                ],
            ];
        }
    }

    // ~

    protected function unmarkedText($text)
    {
        $Inline = $this->inlineText($text);

        return $this->element($Inline['element']);
    }

    //
    // Handlers
    //

    protected function handle(array $Element)
    {
        if (isset($Element['handler'])) {
            if (!isset($Element['nonNestables'])) {
                $Element['nonNestables'] = [];
            }

            if (\is_string($Element['handler'])) {
                $function = $Element['handler'];
                $argument = $Element['text'];
                unset($Element['text']);
                $destination = 'rawHtml';
            } else {
                $function = $Element['handler']['function'];
                $argument = $Element['handler']['argument'];
                $destination = $Element['handler']['destination'];
            }

            $Element[$destination] = $this->{$function}($argument, $Element['nonNestables']);

            if ($destination === 'handler') {
                $Element = $this->handle($Element);
            }

            unset($Element['handler']);
        }

        return $Element;
    }

    protected function handleElementRecursive(array $Element)
    {
        return $this->elementApplyRecursive(fn (array $Element) => $this->handle($Element), $Element);
    }

    protected function handleElementsRecursive(array $Elements)
    {
        return $this->elementsApplyRecursive(fn (array $Element) => $this->handle($Element), $Elements);
    }

    protected function elementApplyRecursive($closure, array $Element)
    {
        $Element = $closure($Element);

        if (isset($Element['elements'])) {
            $Element['elements'] = $this->elementsApplyRecursive($closure, $Element['elements']);
        } elseif (isset($Element['element'])) {
            $Element['element'] = $this->elementApplyRecursive($closure, $Element['element']);
        }

        return $Element;
    }

    protected function elementApplyRecursiveDepthFirst($closure, array $Element)
    {
        if (isset($Element['elements'])) {
            $Element['elements'] = $this->elementsApplyRecursiveDepthFirst($closure, $Element['elements']);
        } elseif (isset($Element['element'])) {
            $Element['element'] = $this->elementsApplyRecursiveDepthFirst($closure, $Element['element']);
        }

        return $closure($Element);
    }

    protected function elementsApplyRecursive($closure, array $Elements)
    {
        foreach ($Elements as &$Element) {
            $Element = $this->elementApplyRecursive($closure, $Element);
        }

        return $Elements;
    }

    protected function elementsApplyRecursiveDepthFirst($closure, array $Elements)
    {
        foreach ($Elements as &$Element) {
            $Element = $this->elementApplyRecursiveDepthFirst($closure, $Element);
        }

        return $Elements;
    }

    protected function element(array $Element)
    {
        if ($this->safeMode) {
            $Element = $this->sanitiseElement($Element);
        }

        // identity map if element has no handler
        $Element = $this->handle($Element);

        $hasName = isset($Element['name']);

        $markup = '';

        if ($hasName) {
            $markup .= '<'.$Element['name'];

            if (isset($Element['attributes'])) {
                foreach ($Element['attributes'] as $name => $value) {
                    if ($value === null) {
                        continue;
                    }

                    $markup .= \sprintf(' %s="', $name).self::escape($value).'"';
                }
            }
        }

        $permitRawHtml = false;

        if (isset($Element['text'])) {
            $text = $Element['text'];
        }
        // very strongly consider an alternative if you're writing an
        // extension
        elseif (isset($Element['rawHtml'])) {
            $text = $Element['rawHtml'];

            $allowRawHtmlInSafeMode = isset($Element['allowRawHtmlInSafeMode']) && $Element['allowRawHtmlInSafeMode'];
            $permitRawHtml = !$this->safeMode || $allowRawHtmlInSafeMode;
        }

        $hasContent = isset($text) || isset($Element['element']) || isset($Element['elements']);

        if ($hasContent) {
            $markup .= $hasName ? '>' : '';

            if (isset($Element['elements'])) {
                $markup .= $this->elements($Element['elements']);
            } elseif (isset($Element['element'])) {
                $markup .= $this->element($Element['element']);
            } elseif (!$permitRawHtml) {
                $markup .= self::escape($text, true);
            } else {
                $markup .= $text;
            }

            $markup .= $hasName ? '</'.$Element['name'].'>' : '';
        } elseif ($hasName) {
            $markup .= ' />';
        }

        return $markup;
    }

    protected function elements(array $Elements)
    {
        $markup = '';

        $autoBreak = true;

        foreach ($Elements as $Element) {
            if (empty($Element)) {
                continue;
            }

            $autoBreakNext = (
                $Element['autobreak'] ?? isset($Element['name'])
            );
            // (autobreak === false) covers both sides of an element
            $autoBreak = $autoBreak ? $autoBreakNext : $autoBreak;

            $markup .= ($autoBreak ? "\n" : '').$this->element($Element);
            $autoBreak = $autoBreakNext;
        }

        return $markup.($autoBreak ? "\n" : '');
    }

    // ~

    protected function li($lines)
    {
        $Elements = $this->linesElements($lines);

        if (isset($Elements[0], $Elements[0]['name']) && !\in_array('', $lines) && $Elements[0]['name'] === 'p'
        ) {
            unset($Elements[0]['name']);
        }

        return $Elements;
    }

    //
    // AST Convenience
    //

    /**
     * Replace occurrences $regexp with $Elements in $text. Return an array of
     * elements representing the replacement.
     */
    protected static function pregReplaceElements($regexp, $Elements, $text): array
    {
        $newElements = [];

        while (preg_match($regexp, (string) $text, $matches, PREG_OFFSET_CAPTURE)) {
            $offset = $matches[0][1];
            $before = substr((string) $text, 0, $offset);
            $after = substr((string) $text, $offset + \strlen((string) $matches[0][0]));

            $newElements[] = ['text' => $before];

            foreach ($Elements as $Element) {
                $newElements[] = $Element;
            }

            $text = $after;
        }

        $newElements[] = ['text' => $text];

        return $newElements;
    }

    //
    // Deprecated Methods
    //

    public function parse($text)
    {
        return $this->text($text);
    }

    protected function sanitiseElement(array $Element)
    {
        static $goodAttribute = '/^[a-zA-Z0-9][a-zA-Z0-9-_]*+$/';
        static $safeUrlNameToAtt = [
            'a'   => 'href',
            'img' => 'src',
        ];

        if (!isset($Element['name'])) {
            unset($Element['attributes']);

            return $Element;
        }

        if (isset($safeUrlNameToAtt[$Element['name']])) {
            $Element = $this->filterUnsafeUrlInAttribute($Element, $safeUrlNameToAtt[$Element['name']]);
        }

        if (!empty($Element['attributes'])) {
            foreach (array_keys($Element['attributes']) as $att) {
                // filter out badly parsed attribute
                if (!preg_match($goodAttribute, (string) $att)) {
                    unset($Element['attributes'][$att]);
                }
                // dump onevent attribute
                elseif (self::striAtStart($att, 'on')) {
                    unset($Element['attributes'][$att]);
                }
            }
        }

        return $Element;
    }

    protected function filterUnsafeUrlInAttribute(array $Element, $attribute)
    {
        foreach ($this->safeLinksWhitelist as $safeLinkWhitelist) {
            if (self::striAtStart($Element['attributes'][$attribute], $safeLinkWhitelist)) {
                return $Element;
            }
        }

        $Element['attributes'][$attribute] = str_replace(':', '%3A', (string) $Element['attributes'][$attribute]);

        return $Element;
    }

    //
    // Static Methods
    //

    protected static function escape($text, $allowQuotes = false)
    {
        return htmlspecialchars((string) $text, $allowQuotes ? ENT_NOQUOTES : ENT_QUOTES, 'UTF-8');
    }

    protected static function striAtStart($string, $needle)
    {
        $len = \strlen((string) $needle);

        if ($len > \strlen((string) $string)) {
            return false;
        }

        return stripos((string) $string, strtolower((string) $needle)) === 0;
    }

    public static function instance($name = 'default')
    {
        if (isset(self::$instances[$name])) {
            return self::$instances[$name];
        }

        $static = new static();

        self::$instances[$name] = $static;

        return $static;
    }

    private static array $instances = [];

    //
    // Fields
    //

    protected $DefinitionData;

    //
    // Read-Only

    protected $specialCharacters = [
        '\\', '`', '*', '_', '{', '}', '[', ']', '(', ')', '>', '#', '+', '-', '.', '!', '|', '~',
    ];

    protected $StrongRegex = [
        '*' => '/^[*]{2}((?:\\\\\*|[^*]|[*][^*]*+[*])+?)[*]{2}(?![*])/s',
        '_' => '/^__((?:\\\\_|[^_]|_[^_]*+_)+?)__(?!_)/us',
    ];

    protected $EmRegex = [
        '*' => '/^[*]((?:\\\\\*|[^*]|[*][*][^*]+?[*][*])+?)[*](?![*])/s',
        '_' => '/^_((?:\\\\_|[^_]|__[^_]*__)+?)_(?!_)\b/us',
    ];

    protected $regexHtmlAttribute = '[a-zA-Z_:][\w:.-]*+(?:\s*+=\s*+(?:[^"\'=<>`\s]+|"[^"]*+"|\'[^\']*+\'))?+';

    protected $voidElements = [
        'area', 'base', 'br', 'col', 'command', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source',
    ];

    protected $textLevelElements = [
        'a', 'br', 'bdo', 'abbr', 'blink', 'nextid', 'acronym', 'basefont',
        'b', 'em', 'big', 'cite', 'small', 'spacer', 'listing',
        'i', 'rp', 'del', 'code',          'strike', 'marquee',
        'q', 'rt', 'ins', 'font',          'strong',
        's', 'tt', 'kbd', 'mark',
        'u', 'xm', 'sub', 'nobr',
        'sup', 'ruby',
        'var', 'span',
        'wbr', 'time',
    ];
}
