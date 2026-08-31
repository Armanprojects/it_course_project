<?php

declare(strict_types=1);

namespace App\Doctrine;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;

/**
 * Exposes the PostgreSQL full-text match operator to DQL:
 *
 *     TS_MATCH(p.searchVector, :query) = TRUE
 *
 * compiles to `p.search_vector @@ to_tsquery('simple', :query)`.
 *
 * DQL has no operator for `@@`, and dropping the whole listing query to native
 * SQL just to search would cost the pagination and hydration the ORM gives us.
 */
final class TsMatchFunction extends FunctionNode
{
    private ?Node $vector = null;

    private ?Node $query = null;

    public function parse(Parser $parser): void
    {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);

        $this->vector = $parser->StringPrimary();

        $parser->match(TokenType::T_COMMA);

        $this->query = $parser->StringPrimary();

        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }

    public function getSql(SqlWalker $sqlWalker): string
    {
        // 'simple' rather than a language configuration: the content is mixed
        // English and Russian, and stemming with the wrong dictionary hurts
        // more than the missing stemming helps.
        return sprintf(
            '(%s @@ to_tsquery(\'simple\', %s))',
            $this->vector?->dispatch($sqlWalker),
            $this->query?->dispatch($sqlWalker),
        );
    }
}
