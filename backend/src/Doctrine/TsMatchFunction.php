<?php

declare(strict_types=1);

namespace App\Doctrine;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;

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
        return sprintf(
            '(%s @@ to_tsquery(\'simple\', %s))',
            $this->vector?->dispatch($sqlWalker),
            $this->query?->dispatch($sqlWalker),
        );
    }
}
