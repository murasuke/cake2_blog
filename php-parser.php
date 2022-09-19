<?php
require_once __DIR__ . '/vendors/autoload.php';
//require 'vendors/autoload.php'; // この行を追加
use PhpParser\{
    ParserFactory,
    Parser,
    PrettyPrinter,
    NodeTraverser,
    NodeVisitor,
    NodeDumper,
    NodeVisitor\NameResolver,
    Lexer,
    Error
};

use PhpParser\Node;
use PhpParser\Node\Stmt\Function_;
// use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

$configs = [
    [
        'target' => 'app/Controller',
        //'target' => 'app/View/Users',
        'exclude' => ['.git', 'vendors', 'plugins'],
        'visitors' => [
            'visitor\ListMethod',
        ]
    ],
];

class FileTraverser //extends NodeVisitorAbstract
{
    private $nest = 0;
    private function tab($offset = 0) {
        return str_repeat(" ", ($this->nest + $offset) * 4);
    }

    public function traverse(Array $stmts) {
        foreach( $stmts as $stmt) {
            $this->stmt($stmt);
        }
    }

    public function enterNode(Node $node)
    {
        // echo print_r($node);
        // echo $node->expr->name;
        $nodeType = substr(get_class($node),15) ?? "";
        $exprType = isset($node->expr) ? substr(get_class($node->expr), 15) : "";
        switch ($nodeType) {
            case "Stmt\Class_":
                echo $nodeType ."::" . $node->name->name . "\n";
                $this->listStmts($node->stmts);
                break;
            // case "Expr\Array_":
            //     echo "\t".$nodeType;
            //     $this->listArrays($node->items);
            //     echo "\n";
            //     break;
            // case "Stmt\Expression":
            //     if (isset($node->expr->name)) {
            //         echo "\t" . $nodeType . "::" . $node->expr->name . "\n";
            //     }

            //     $this->expr($node->expr);
            case "Stmt\InlineHTML":
                echo $nodeType ."::";
                break;
            case "Stmt\Echo_":
                //$this->listExpr($node->exprs);
                break;
            case "Name":
                foreach($node->parts as $part){
                    echo "\n";
                    echo $part;
                }
                break;
            case "Arg":
                if( $node->name ){
                    echo "\n";
                    echo $node->name;
                }
                $this->expr($node->value);
                break;
            case "Identifier":
                echo "\n";
                echo $node->name;
                break;
            default:
                if (preg_match("/^(Expr|Scalar)\\.*/", $nodeType)) {
                    $this->expr($node);
                } else if(preg_match("/^Stmt\\.*/", $nodeType)) {
                    $this->stmt($node);
                } else {
                    echo "break";
                }
                break;

        }
    }


    private function stmt($stmt){
        echo "\n";
        $exprType = isset($stmt) ? substr(get_class($stmt), 15) : "";
        $this->nest++;
        switch ($exprType) {
            case "Stmt\Class_":
                echo $exprType ."::" . $stmt->name->name . "\n";
                $this->listStmts($stmt->stmts);
                break;

            case "Stmt\Property":
                echo $this->tab().$exprType."::";
                $this->listProps($stmt->props);

                break;
            case "Stmt\ClassMethod":
                echo $this->tab().$exprType."::".$stmt->name->name;
                $this->listParams($stmt->params);
                $this->listStmts($stmt->stmts);
                break;
            case "Stmt\Expression":
                echo $this->tab().$exprType;
                $this->expr($stmt->expr);
                break;
            case "Stmt\If_":
                echo $this->tab().$exprType;
                $this->expr($stmt->cond);
                $this->listStmts($stmt->stmts);
                if( !empty($stmt->elseifs)){
                    $this->listStmts($stmt->elseifs);
                }
                if( !empty($stmt->else)){
                    $this->stmt($stmt->else);
                }
                break;
            case "Stmt\Else_":
                echo $this->tab().$exprType;
                $this->listStmts($stmt->stmts);
                break;
            case "Stmt\Switch_":
                echo $this->tab().$exprType;
                $this->expr($stmt->cond);
                $this->listStmts($stmt->cases);
                break;
            case "Stmt\Case_":
                echo $this->tab().$exprType;
                $this->expr($stmt->cond);
                $this->listStmts($stmt->stmts);
                break;
            case "Stmt\For_":
                echo $this->tab().$exprType;
                echo "::";
                $this->listExpr($stmt->init);
                echo "; ";
                $this->listExpr($stmt->cond);
                echo "; ";
                $this->listExpr($stmt->loop);
                $this->listStmts($stmt->stmts);
                break;
            case "Stmt\Foreach_":
                echo $this->tab().$exprType;
                echo "::";
                $this->expr($stmt->expr);
                echo " as ";
                $this->expr($stmt->keyVar);
                echo " => ";
                $this->expr($stmt->valueVar);
                $this->listStmts($stmt->stmts);
                break;
            case "Stmt\Continue_":
            case "Stmt\Break_":
                echo $this->tab().$exprType;
                if ($stmt->num) {
                    echo "::$stmt->num";
                }
                break;
            case "Stmt\Return_":
                echo $this->tab().$exprType;
                $this->expr($stmt->expr);
                break;
            case "Stmt\TryCatch":
                echo $this->tab().$exprType;
                $this->listStmts($stmt->stmts);
                $this->listStmts($stmt->catches);
                if($stmt->finally){
                    $this->listStmts($stmt->finally);
                }
                break;
            case "Stmt\Throw_":
                echo $this->tab().$exprType;
                $this->expr($stmt->expr);
                break;
            case "Stmt\Catch_":
                echo $this->tab().$exprType;
                echo "::".$stmt->types[0]->parts[0]; //FIXME
                break;
            case "Stmt\Echo_":
                echo $this->tab().$exprType;
                $this->listExpr($stmt->exprs);
                break;
            case "Stmt\Unset_":
                echo $this->tab().$exprType;
                $this->listExpr($stmt->vars);
                break;
            case "Stmt\Nop":
                echo $this->tab().$exprType;
                break;
            case "Stmt\InlineHTML":
                echo $this->tab().$exprType;
                break;
            default:
                echo "★".$exprType;
                break;
        }
        $this->nest--;
    }

    private function expr($expr){
        if (is_null($expr)){
            return;
        }
        echo "\n";
        $this->nest++;
        $exprType = isset($expr) ? substr(get_class($expr),15) : "";
        switch ($exprType) {
            case "Scalar\String_":
                echo $this->tab().$exprType."::'".$expr->value."'";
                break;
            case "Scalar\LNumber":
                echo $this->tab().$exprType."::".$expr->value;
                break;
            case "Expr\MethodCall":
                echo $this->tab().$exprType;
                $this->methodCall($expr);
                break;
            case "Expr\StaticCall":
                echo $this->tab().$exprType;
                $this->methodCall($expr);
                break;
            case "Expr\FuncCall":
                echo $this->tab().$exprType;
                $this->methodCall($expr);
                break;
            case "Expr\Assign":
            case "Expr\AssignOp\Plus":
            case "Expr\AssignOp\Concat":
                echo $this->tab().$exprType;
                $assignType = substr(get_class($expr->var),15);
                if ($assignType === "Expr\ArrayDimFetch" || $assignType === "Expr\PropertyFetch") {
                    $this->expr($expr->var);
                    echo " = ";
                }else{
                    echo "::$". $expr->var->name." = ";
                }
                echo $this->expr($expr->expr);
                break;
            case "Expr\ArrayItem":
                echo $this->tab().$exprType;
                if ($expr->key) {
                    $this->expr($expr->key);
                    echo "=>";
                }
                $this->expr($expr->value);
                break;
            case "Expr\Variable":
                echo $this->tab().$exprType;
                echo "::$".$expr->name;
                break;
            case "Expr\Array_":
                echo $this->tab().$exprType;
                $this->listArrays($expr->items);
                break;
            case "Expr\ConstFetch":
                echo $this->tab().$exprType;
                echo "::".$expr->name->parts[0];
                break;
            case "Expr\ArrayDimFetch":
                echo $this->tab().$exprType;
                $this->expr($expr->var);
                if ($expr->dim){
                    if (substr(get_class($expr->dim),15) === "Scalar\String_") {
                        echo "[".$expr->dim->value."]";
                    } else {
                        $this->expr($expr->dim);
                    }
                }
                break;
            case "Expr\New_":
                echo $this->tab().$exprType."::";
                echo $expr->class->parts[0];
                $this->listArgs($expr->args);
                break;
            case "Expr\PropertyFetch":
                echo $this->tab().$exprType."::";
                echo "$".$expr->var->name."->".$expr->name->name;
                break;
            case "Expr\BooleanNot":
                echo $this->tab().$exprType."";
                $this->expr($expr->expr);
                break;
            case "Expr\Empty_":
                echo $this->tab().$exprType;
                $this->expr($expr->expr);
                break;
            case "Expr\BinaryOp\BooleanOr":
                echo $this->tab().$exprType;
                $this->expr($expr->left);
                $this->expr($expr->right);
                break;
            case "Expr\BinaryOp\BooleanAnd":
                echo $this->tab().$exprType;
                $this->expr($expr->left);
                $this->expr($expr->right);
                break;
            case "Expr\Isset_":
                echo $this->tab().$exprType;
                foreach($expr->vars as $var){
                    $this->expr($var);
                }
                break;
            case "Expr\BinaryOp\Identical":
            case "Expr\BinaryOp\NotIdentical":
            case "Expr\BinaryOp\Plus":
            case "Expr\BinaryOp\Minus":
            case "Expr\BinaryOp\Mul":
            case "Expr\BinaryOp\Div":
            case "Expr\BinaryOp\Equal":
            case "Expr\BinaryOp\NotEqual":
            case "Expr\BinaryOp\Greater":
            case "Expr\BinaryOp\Smaller":
            case "Expr\BinaryOp\GreaterOrEqual":
            case "Expr\BinaryOp\SmallerOrEqual":
            case "Expr\BinaryOp\Concat":
                echo $this->tab().$exprType;
                $this->expr($expr->left);
                $this->expr($expr->right);
                break;
            case "Expr\Ternary":  //三項演算子
                echo $this->tab().$exprType;
                $this->expr($expr->cond);
                echo "?";
                $this->expr($expr->if);
                echo ":";
                $this->expr($expr->else);
                break;
             case "Expr\PostInc":
                echo $this->tab().$exprType;
                $this->expr($expr->var);
                break;
            case "Expr\UnaryMinus":
                echo $this->tab().$exprType;
                $this->expr($expr->expr);
                break;
            case "Expr\Cast\Int_":
            case "Expr\Cast\Array_":
                echo $this->tab().$exprType;
                $this->expr($expr->expr);
                break;
            default:
                echo "★".$exprType;
                break;
        }
        //echo "\n";
        $this->nest--;
    }


    private function methodCall($method) {

        if (is_null($method)) {
            return;
        } else if(isset($method->var) ) {
            $this->expr($method->var);
            echo  "->".$method->name;
        } else if (isset($method->name)) {
            echo  "::$".$method->name;
        }

        if (isset($method->args)) {
            $this->listArgs($method->args);
        }

    }

    private function listStmts($stmts)
    {
        if (empty($stmts)) {
            return;
        }

        foreach ($stmts as $stmt) {
            $this->stmt($stmt);
        }
    }

    private function listExpr($exprs)
    {
        if (empty($exprs)) {
            return;
        }

        foreach ($exprs as $expr) {
            $this->expr($expr);
        }
    }

    private function listArrays($items)
    {
        if (empty($items)) {
            return;
        }

        foreach ($items as $item) {
            $this->expr($item);
        }
    }

    private function listParams(array $params)
    {
        $this->nest++;
        echo "\n".$this->tab()."(";

        if (!empty($params)) {
            $sep = "";
            foreach ($params as $param) {
                $exprType = get_class($param->var) ?? "";
                // $this->expr($param->var);
                echo  "\n".$this->tab(1)."$".$param->var->name;
                $sep = ", ";
            }
        }

        echo "\n".$this->tab().")";
        $this->nest--;

    }

    private function listProps(array $props)
    {
        if (empty($props)) {
            return;
        }

        $sep = "";
        foreach ($props as $prop) {
            echo  $sep . $prop->name;
            $sep = ", ";
        }

        $this->expr(($prop->default));

    }

    private function listArgs(array $args)
    {
        $this->nest++;
        echo "\n".$this->tab()."(";

        if (!empty($args)) {
            $sep = "";
            foreach ($args as $arg) {
                $exprType = get_class($arg->value) ?? "";
                $this->expr($arg->value);
                //echo  $sep . $arg->var->name;
                $sep = ", ";
            }
        }

        echo "\n".$this->tab().")";
        $this->nest--;
    }

}


$nodeDumper = new NodeDumper;
$lexer = new Lexer\Emulative([
    'usedAttributes' => [
        'comments',
        'startLine', 'endLine',
        'startTokenPos', 'endTokenPos',
    ],
]);

$parser = (new ParserFactory)->create(
    ParserFactory::PREFER_PHP7,
    $lexer
);

$traverser = new NodeTraverser;
$traverser->addVisitor(new NodeVisitor\CloningVisitor());


foreach ($configs as $config) {
    $target = $dir . $config['target'];
    $exclude = $config['exclude'] ?? [];

    echo "target:{$target}\n";

    // $config['exclude']に記載したディレクトリを変換対象外にするフィルタを定義します
    $filter = function ($file, $key, $iterator) use ($exclude) {
        if ($iterator->hasChildren() && !in_array($file->getFilename(), $exclude)) {
            return true;
        }
        return $file->isFile();
    };
    $files = new \RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator(
                $target,
                RecursiveDirectoryIterator::SKIP_DOTS
            ),
            $filter
        )
    );
    // .php なファイルだけすべて取得します
    $files = new \RegexIterator($files, '/\.(php|ctp)$/');
    //$files = new \RegexIterator($files, '/index\.(php|ctp)$/');

    // $config['visitors']に記載したVisitorを追加します
    // foreach ($config['visitors'] as $visitor) {
    //     echo "visitor:{$visitor}\n";
    //     $traverser->addVisitor(new $visitor());
    // }

    // 先程取得したファイルをぐるぐるします
    foreach ($files as $file) {
        try {
            echo "\n";
            echo $file . PHP_EOL;

            // 変換元コードの取得
            $code = file_get_contents($file->getPathName());

            // こちらもformatting-preserving-pretty-printingの書き方です
            $oldStmts = $parser->parse($code);

            $traverser = new FileTraverser();
            $traverser->traverse($oldStmts);

            // $oldTokens = $lexer->getTokens();

            // // パース済みの元ASTをVisitorが走査して変換したASTを生成します
            // $newStmts = $traverser->traverse($oldStmts);
            // // AST → PHPのコード
            // //$newCode = $printer->printFormatPreserving($newStmts, $oldStmts, $oldTokens);

            // ##### Debug #####
            // //echo $nodeDumper->dump($oldStmts);
            // //echo $nodeDumper->dump($newStmts);
            // // pretty print
            // //echo $newCode;
            // // write the converted file to the target directory
            // #################

            // // 変換した内容で、ファイルの上書き
            // // file_put_contents(
            // //     $file->getPathname(),
            // //     // RequireToUseで末尾に use〜;;とコロンが2つついてしまうので、修正。(不本意)
            // //     str_replace(';;', ';', $newCode)
            // // );
        } catch (PhpParser\Error $e) {
            echo 'Parse Error: ', $e->getMessage();
        }
    }
}
