<?php

declare(strict_types=1);

namespace App\Statements;

final readonly class StatementReader
{
    /**
     * @param  list<StatementParser>  $parsers
     */
    public function __construct(
        private PdfTextExtractor $extractor,
        private array $parsers,
    ) {}

    public function read(string $path): ParsedStatement
    {
        $text = $this->extractor->extract($path);

        foreach ($this->parsers as $parser) {
            if ($parser->supports($text)) {
                $statement = $parser->parse($text);
                BalanceCheck::verify($statement);

                return $statement;
            }
        }

        throw StatementException::unsupported();
    }
}
