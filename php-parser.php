<?php
if (file_exists(__DIR__ . '/vendors/autoload.php')) {
    require_once __DIR__ . '/vendors/autoload.php';
}else{
    require_once __DIR__ . '/vendor/autoload.php';
}

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
        // 'visitors' => [
        //     'visitor\ListMethod',
        // ]
    ],
    [
        'target' => 'app/View',
        'exclude' => ['.git', 'vendors', 'plugins'],
    ],
];

    // Controller、Viewを全て解析して配列にセットする
    // Controllerに対して下記を行う
    // ①action⇒View,Elementの一覧を取得する
    //   $this->methods[]から、Viewを取得
    //   ViewからElementを取得（再帰)
    //     ⇒ actionで利用しているView、Elementを抜き出す
    // ②View、Elementから逆順で検索する
    //  ⇒逆順で走査することも可能だが、①の結果からまとめることが可能(なはず)
class AnalyzedClass {
    public const CAKE_TYPE_CONTROLLER = 0;
    public const CAKE_TYPE_VIEW = 1;
    public const CAKE_TYPE_ELEMENT = 2;
    public const CAKE_TYPE_MODEL = 3;


    public $filePath = ""; // appからの相対パス
    public $fileName = "";
    public $className = "";
    public $fileType = AnalyzedClass::CAKE_TYPE_CONTROLLER;

    public $methods = [];

    private $currentName = "";

    public function __construct($filePath) {
        $this->filePath = $filePath;// preg_replace("/^.*/(app/.*)", "$1", $filePath);
        $this->fileName = basename($filePath);
        if (preg_match('/\.ctp$/',$filePath)) {
            $this->fileType = AnalyzedClass::CAKE_TYPE_VIEW;
        }
        $this->currentName = $filePath;
    }

    public function addMethod($name) {
        $this->currentName = $name;
        $this->methods[$this->currentName] = new AnalyzeMethod();
    }

    public function addMethodCall($target, $name, $args) {
        $this->methods[$this->currentName]->methodCall[] = [$target."->".$name => $this->args($args)];
    }
    public function addStaticCall($target, $name, $args) {
        $this->methods[$this->currentName]->staticCall[] = [$target."::".$name => $this->args($args)];
    }
    public function addFuncCall($name, $args) {
        $this->methods[$this->currentName]->funcCall[] = [$name => $this->args($args)];
    }

    public function args($args) {
        if (empty($args)) {
            return [];
        }

        $ret = [];
        foreach ($args as $arg) {
            $ftr = new ClassAnalizer($this->filePath);
            $ret[] = $ftr->expr($arg->value);
            $tmp = $ftr->analyzedData;
        }
        return $ret;
    }
}

class AnalyzeMethod{
    public $methodCall = [];
    public $staticCall = [];
    public $funcCall = [];
}

/**
 * 優先度
 * 1:actionメソッド毎に表示するViewを取得(リダイレクトや別actionの呼び出し(requestAction)を考慮しない)
 * 2:ViewからElementを取得
 * 3:Elementを再帰的に取得する
 * 4:action->View->Elementを芋づる式に取得
 * 3:リダイレクトを考慮
 * 4:requestActionを考慮
 * 5:別アクションの直接呼出しを考慮
 * 6:コンポーネントを考慮？？？
 */
class Extractor{
    public $analyzedArray = [];
    public function __construct($result) {
        $this->analyzedArray = $result;
    }

    public function extractRender($constoller = "", $action = "") {
        foreach( $this->analyzedArray as $analyzedData) {
            foreach($analyzedData->methods as $parentName => $parentMethod) {
                if ($constoller !== "" && $constoller !== $analyzedData->className) {
                    continue;
                }

                if ($action !== "" && $action !==$parentName ) {
                    continue;
                }
                echo "$parentName() ▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼\n";
                $existRender = false;
                foreach($parentMethod as $method) {

                    foreach($method as $seqNo => $callee) {
                        $argstr = "";
                        foreach($callee as $calleeName => $args) {
                            foreach($args as $arg){
                                $argstr.= (empty($argstr)?"":",").$arg;
                            }
                        }
                        //echo "\t$seqNo: $calleeName->($argstr)\n";
                        if (preg_match('/->render/',$calleeName)) {
                            $existRender = true;
                            echo "\trender:$argstr\n";
                            $this->extractView($argstr);
                        }
                    }
                }
                echo "\trender:$parentName\n";
                $classPath = str_replace("app/Controller/","",  $analyzedData->filePath);
                $classPath = str_replace("Controller.php","",  $classPath);
                $this->extractView($classPath, $parentName);
                echo "$parentName() ▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲\n";
            }
        }


    }

    private function tab($depth, $offset = 0) {
        return str_repeat(" ", ($depth + $offset) * 4);
    }

    public function extractView($classPath, $view ="", $depth = 0) {
        $depth++;
        foreach( $this->analyzedArray as $analyzedData) {
            if ($analyzedData->fileType !== AnalyzedClass::CAKE_TYPE_VIEW) {
                continue;
            }

            if (basename($analyzedData->fileName,".ctp") !== $view) {
                continue;
            }

            if ($analyzedData->filePath === "app/View/$classPath/$view.ctp")
            {
                echo "";
            }else{
                echo "";
            }

            foreach($analyzedData->methods as $parentName => $parentMethod) {
                // echo $this->tab($depth)."$parentName() ▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼▼\n";
                $existRender = false;
                foreach($parentMethod as $method) {

                    foreach($method as $seqNo => $callee) {
                        $argstr = "";
                        foreach($callee as $calleeName => $args) {
                            foreach($args as $arg){
                                $argstr.= (empty($argstr)?"":",").$arg;
                            }
                        }
                        //echo "\t$seqNo: $calleeName->($argstr)\n";
                        if (preg_match('/->element/',$calleeName)) {
                            $existRender = true;
                            echo $this->tab($depth)."\t$view.ctp element:$argstr\n";
                            $argstr = str_replace("'","",$argstr);
                            $elemPath = "Elements";
                            $this->extractView($elemPath, $argstr, $depth);
                        }
                    }
                }
                //echo "\trender:$parentName\n";
                // echo $this->tab($depth)."$parentName() ▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲\n";
            }
        }
        $depth--;
    }

}


class ClassAnalizer //extends NodeVisitorAbstract
{
    private $nest = 0;
    /** @var AnalyzedClass */
    public $analyzedData;


    /**
     * @@param string $filePath
     */
    public function __construct($filePath) {
        $this->analyzedData = new AnalyzedClass($filePath);
    }

    private function tab($offset = 0) {
        return str_repeat(" ", ($this->nest + $offset) * 4);
    }

    public function traverseStmts($stmts)
    {
        if (empty($stmts)) {
            return;
        }

        foreach ($stmts as $stmt) {
            $this->stmt($stmt);
        }
        return $this->analyzedData;
        //print_r($this->result);
    }


    public function stmt($stmt){
        echo "\n";
        $exprType = isset($stmt) ? substr(get_class($stmt), 15) : "";
        $this->nest++;
        switch ($exprType) {
            case "Stmt\Class_":
                echo $exprType ."::" . $stmt->name->name . "\n";
                $this->analyzedData->className = $stmt->name->name;
                $this->traverseStmts($stmt->stmts);
                break;

            case "Stmt\Property":
                echo $this->tab().$exprType."::";
                $this->listProps($stmt->props);

                break;
            case "Stmt\ClassMethod":
                echo $this->tab().$exprType."::".$stmt->name->name;
                $this->analyzedData->addMethod($stmt->name->name);
                $this->listParams($stmt->params);
                $this->traverseStmts($stmt->stmts);
                break;
            case "Stmt\Expression":
                echo $this->tab().$exprType;
                $this->expr($stmt->expr);
                break;
            case "Stmt\If_":
                echo $this->tab().$exprType;
                $this->expr($stmt->cond);
                $this->traverseStmts($stmt->stmts);
                if( !empty($stmt->elseifs)){
                    $this->traverseStmts($stmt->elseifs);
                }
                if( !empty($stmt->else)){
                    $this->stmt($stmt->else);
                }
                break;
            case "Stmt\Else_":
                echo $this->tab().$exprType;
                $this->traverseStmts($stmt->stmts);
                break;
            case "Stmt\Switch_":
                echo $this->tab().$exprType;
                $this->expr($stmt->cond);
                $this->traverseStmts($stmt->cases);
                break;
            case "Stmt\Case_":
                echo $this->tab().$exprType;
                $this->expr($stmt->cond);
                $this->traverseStmts($stmt->stmts);
                break;
            case "Stmt\For_":
                echo $this->tab().$exprType;
                echo "::";
                $this->traverseExprs($stmt->init);
                echo "; ";
                $this->traverseExprs($stmt->cond);
                echo "; ";
                $this->traverseExprs($stmt->loop);
                $this->traverseStmts($stmt->stmts);
                break;
            case "Stmt\Foreach_":
                echo $this->tab().$exprType;
                echo "::";
                $this->expr($stmt->expr);
                echo " as ";
                $this->expr($stmt->keyVar);
                echo " => ";
                $this->expr($stmt->valueVar);
                $this->traverseStmts($stmt->stmts);
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
                $this->traverseStmts($stmt->stmts);
                $this->traverseStmts($stmt->catches);
                if($stmt->finally){
                    $this->traverseStmts($stmt->finally);
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
                $this->traverseExprs($stmt->exprs);
                break;
            case "Stmt\Unset_":
                echo $this->tab().$exprType;
                $this->traverseExprs($stmt->vars);
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

    public function traverseExprs($exprs)
    {
        if (empty($exprs)) {
            return;
        }

        foreach ($exprs as $expr) {
            $this->expr($expr);
        }
    }

    public function expr($expr){
        $ret = "";
        if (is_null($expr)){
            return;
        }
        echo "\n";
        $this->nest++;
        $exprType = isset($expr) ? substr(get_class($expr),15) : "";
        switch ($exprType) {
            case "Scalar\String_":
                echo $this->tab().$exprType."::'".$expr->value."'";
                $ret .= "'".$expr->value."'";
                break;
            case "Scalar\LNumber":
                echo $this->tab().$exprType."::".$expr->value;
                $ret .= $expr->value;
                break;
            case "Expr\MethodCall":
                echo $this->tab().$exprType;
                $ret .= $this->methodCall($expr);
                break;
            case "Expr\StaticCall":
                echo $this->tab().$exprType;
                $ret .= $this->methodCall($expr);
                break;
            case "Expr\FuncCall":
                echo $this->tab().$exprType;
                $ret .= $this->methodCall($expr);
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
                    $ret .= $this->expr($expr->key);
                    echo "=>";
                    $ret .= "=>";
                }
                $ret .= $this->expr($expr->value);
                break;
            case "Expr\Variable":
                echo $this->tab().$exprType;
                echo "::$".$expr->name;
                $ret .= "$".$expr->name;
                break;
            case "Expr\Array_":
                echo $this->tab().$exprType;
                $ret .= $this->listArrays($expr->items);
                break;
            case "Expr\ConstFetch":
                echo $this->tab().$exprType;
                echo "::".$expr->name->parts[0];
                $ret .= $expr->name->parts[0];
                break;
            case "Expr\ArrayDimFetch":
                echo $this->tab().$exprType;
                $ret = $this->expr($expr->var);
                if ($expr->dim){
                    if (substr(get_class($expr->dim),15) === "Scalar\String_") {
                        echo "[".$expr->dim->value."]";
                        $ret .= "[".$expr->dim->value."]";
                    } else {
                        $ret .= $this->expr($expr->dim);
                    }
                }
                break;
            case "Expr\PropertyFetch":
                echo $this->tab().$exprType."::";
                echo "$".$expr->var->name."->".$expr->name->name;
                $ret = "$".$expr->var->name."->".$expr->name->name;
                break;
            case "Expr\New_":
                echo $this->tab().$exprType."::";
                echo $expr->class->parts[0];
                $this->listArgs($expr->args);
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
        return $ret;
    }


    private function methodCall($method) {
        $ret="";
        $target = "";
        $funcNm = "";
        if (is_null($method)) {
            return;
        } else if(isset($method->var) ) {
            $target .= $this->expr($method->var);
            $funcNm .= $method->name->name;
            $ret .= $target."->".$funcNm;
            echo  "->".$method->name->name;
        } else if (isset($method->name)) {
            echo  "::$".$method->name;
            $target .= (isset($method->class)? $method->class->parts[0]:"");
            $funcNm .= "".$method->name;
            $ret .= $target."::".$funcNm;
        }


        if (isset($method->args)) {
            $ret .= "(".$this->listArgs($method->args).")";
        }

        switch (substr(get_class($method),15)) {
            case "Expr\MethodCall":
                $this->analyzedData->addMethodCall($target, $funcNm, $method->args);
                break;
            case "Expr\StaticCall":
                $this->analyzedData->addStaticCall($target, $funcNm, $method->args);
                break;
            case "Expr\FuncCall":
                $this->analyzedData->addFuncCall($funcNm, $method->args);
                break;
        }
        return $ret;
    }


    private function listArrays($items)
    {
        $ret = "";
        if (empty($items)) {
            return;
        }

        $sep = "";
        $ret .= "[";
        foreach ($items as $item) {
            $ret .= $sep.$this->expr($item);
            $sep = ",";
        }
        $ret .= "]";
        return $ret;
    }

    private function listParams(array $params)
    {
        $this->nest++;
        echo "\n".$this->tab()."(";

        if (!empty($params)) {
            $sep = "";
            foreach ($params as $param) {
                $exprType = get_class($param->var) ?? "";
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
        $ret = "";
        $this->nest++;
        echo "\n".$this->tab()."(";

        if (!empty($args)) {
            $sep = "";
            foreach ($args as $arg) {
                $exprType = get_class($arg->value) ?? "";
                $ret .=$sep.$this->expr($arg->value);
                $sep = ", ";
            }
        }

        echo "\n".$this->tab().")";
        $this->nest--;
        return $ret;
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

// $traverser = new NodeTraverser;
// $traverser->addVisitor(new NodeVisitor\CloningVisitor());
$analyaedArray = [];

foreach ($configs as $config) {
    $target =  $config['target'];
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
    $files = new RegexIterator($files, '/\.(php|ctp)$/');
    //$files = new RegexIterator($files, '/CoursesController\.(php|ctp)$/');

    // $config['visitors']に記載したVisitorを追加します
    // foreach ($config['visitors'] as $visitor) {
    //     echo "visitor:{$visitor}\n";
    //     $traverser->addVisitor(new $visitor());
    // }





    // 先程取得したファイルをぐるぐるします
    foreach ($files as $file) {
        try {
            ob_start();

            echo "\n";
            echo $file . PHP_EOL;

            // 変換元コードの取得
            $code = file_get_contents($file->getPathName());

            // こちらもformatting-preserving-pretty-printingの書き方です
            $oldStmts = $parser->parse($code);

            $analyzer = new ClassAnalizer($file->getPathName());
            $analyzedData = $analyzer->traverseStmts($oldStmts);
            // //print_r($traverser->result);

            $analyaedArray += [$analyzedData->filePath => $analyzedData];
            // $ext = new Extractor($analyzer->analyzedData);
            ob_end_clean();

            echo "\n\n";
            echo $file->getPathName()."\n";
            echo "===========================================================\n";
            // $ext->extractRender("view");


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


    $ext = new Extractor($analyaedArray);
    $ext->extractRender("", "");
