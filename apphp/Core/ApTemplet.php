<?php
/**
 * Created by PhpStorm.
 * User: APOCALYPSE
 * Date: 2017/11/28
 * Time: 22:21
 */

namespace apphp\Core;


class ApTemplet
{

    // 模板路径
    protected $template_dir = ROOT_PATH.'resource/view/';
    protected $cache_dir = ROOT_PATH.'runtime/cache/';
    protected $compilers = [
        "JsEchos",
        "Echos",
        "EscapedEchos",
        "Statements",
    ];


    /**
     * @access protected 解析模板点字符串为路径
     * @param string $view 模板名称
     * @return string 路径
     * */
    protected function analyzeDot($view)
    {
        $view_name = str_replace('.','/',$view); // 把.替换成/

        return $view_name;
    }


    /**
     * @access protected 查看文件是否给修改过，若未修改过则使用缓存
     * @param string $path 文件路径
     * @return string 返回图片缓存的路径
     * */
    protected function isExpired($path)
    {
        $templet_file = $this->getCompiledPath($path);
        if(!file_exists($templet_file))
        {
            return true;
        }

        return filemtime($path) > filemtime($templet_file);
    }

    /**
     * @access protected 生成缓存文件
     * @param string $path 文件名
     * @return bool 是否生成成功
     * */
    protected function getCompiledPath($path)
    {
        return $this->cache_dir.md5($path);
    }

    /**
     * @access protected 模板引擎显示
     * @param string $view 模板名称
     * @param array $params 参数
     * @return bool
     * */
    public function show($view, $params = []): string
    {
        if (!is_string($view) || !preg_match('/\A[A-Za-z0-9_-]+(?:\.[A-Za-z0-9_-]+)*\z/', $view)) {
            throw new \InvalidArgumentException('Invalid view name');
        }
        if ($params === null) { $params = []; }
        if (!is_array($params)) { throw new \InvalidArgumentException('View data must be an array'); }
        $file = realpath($this->template_dir . $this->analyzeDot($view) . '.html');
        $directory = realpath($this->template_dir);
        if ($file === false || $directory === false || strpos($file, $directory . DIRECTORY_SEPARATOR) !== 0 || !is_file($file)) {
            throw new \RuntimeException('View not found: ' . $view);
        }
        if (!is_dir($this->cache_dir) && !mkdir($this->cache_dir, 0700, true) && !is_dir($this->cache_dir)) {
            throw new \RuntimeException('Cannot create template cache directory');
        }
        clearstatcache(true, $file);
        $cache_file = $this->getCompiledPath($file);
        clearstatcache(true, $cache_file);
        if ($this->isExpired($file)) {
            $file_content = file_get_contents($file);
            if ($file_content === false) { throw new \RuntimeException('Cannot read view: ' . $view); }
            $result = '';
            foreach (token_get_all($file_content) as $token) {
                if (is_array($token)) {
                    list($id, $content) = $token;
                    if ($id == T_INLINE_HTML) {
                        foreach ($this->compilers as $type) { $content = $this->{'compile' . $type}($content); }
                    }
                    $result .= $content;
                } else { $result .= $token; }
            }
            // Write completely before publishing, so readers never include partial PHP.
            $temporary = tempnam($this->cache_dir, 'view-');
            if ($temporary === false) { throw new \RuntimeException('Cannot create template cache'); }
            try {
                if (file_put_contents($temporary, $result) === false || !rename($temporary, $cache_file)) {
                    throw new \RuntimeException('Cannot save template cache');
                }
                if (function_exists('opcache_invalidate')) { opcache_invalidate($cache_file, true); }
            } finally { if (is_file($temporary)) { unlink($temporary); } }
        }
        return $this->renderCompiled($cache_file, $params);
    }

    private function renderCompiled($template_file, array $template_data): string
    {
        // Static scope keeps template variables separate from compiler state and $this.
        $render = static function($template_file, array $template_data) {
            extract($template_data, EXTR_SKIP);
            require $template_file;
        };
        $level = ob_get_level();
        ob_start();
        try {
            $render($template_file, $template_data);
            return ob_get_clean();
        } catch (\Throwable $error) {
            while (ob_get_level() > $level) { ob_end_clean(); }
            throw $error;
        }
    }

    /**
     * @access protected 用于处理Js的模板引擎
     * @param string $content 内容
     * @return bool
     * */
    protected function compileJsEchos($content)
    {
        return preg_replace('/@{{ (\S+) }}/' , '<?php echo chr(123) . chr(123) . " " . "$1"  . " " . chr(125) . chr(125); ?>', $content);
    }

    /**
     * @access protected 不处理内容进行输出
     * @param string $content 内容
     * @return bool
     * */
    protected function compileEchos($content)
    {
        return preg_replace('/{!! (\S+) !!}/' , '<?php echo $1 ?>', $content);
    }

    /**
     * @access protected 处理掉特殊字符串
     * @param string $content 内容
     * @return bool
     * */
    protected function compileEscapedEchos($content)
    {
        return preg_replace('/{{ (\S+) }}/' , '<?php echo htmlentities($1) ?>',$content);
    }

    /**
     * @access protected 正则表达式匹配@xx()中的语句
     * @param string $content 需要匹配的内容
     * @return bool
     * */
    protected function compileStatements($content)
    {
        return preg_replace_callback(
            '/\B@(@?\w+(?:::\w+)?)([ \t]*)(\( ( (?>[^()]+) | (?3) )* \))?/x', function ($match){
            return $this->compileStatement($match);
        }, $content
        );
    }

    /**
     *  查找符合的条件语句
     *
     *
     * @match 由compileStatements方法传来的
     * */
    protected function compileStatement($match)
    {
        if (strpos($match[1], '@') !== false)
        {
            $match[0] = isset($match[3]) ? $match[1].$match[3] : $match[1];
        }
        elseif (method_exists($this, $method = 'compile'.ucfirst($match[1])))
        {
            $match[0] = $this->$method(isset($match[3]) ? $match[3] : null);
        }

        return isset($match[3]) ? $match[0] : $match[0].$match[2];
    }

    /**
     * @access protected 模板引擎include中的应用
     * @param string $expression 文件名
     * @return bool
     * */
    protected function compileInclude($expression)
    {
        $file = "('" . ROOT_PATH . 'resource/view/' . substr($expression, 2);

        return "<?php include{$file} ?>";
    }

    /**
     * @access protected 模板引擎if中的应用
     * @param string $expression 条件
     * @return bool
     * */
    protected function compileIf($expression)
    {
        return "<?php if{$expression}: ?>";
    }

    /**
     * @access protected 模板引擎elseif中的应用
     * @param string $expression 条件
     * @return bool
     * */
    protected function compileElseif($expression)
    {
        return "<?php elseif{$expression}: ?>";
    }

    /**
     * @access protected 模板引擎else中的应用
     * @param string $expression 空
     * @return bool
     * */
    protected function compileElse($expression)
    {
        return "<?php else: ?>";
    }

    /**
     * @access protected 模板引擎endif中的应用
     * @param string $expression 空
     * @return bool
     * */
    protected function compileEndif($expression)
    {
        return "<?php endif; ?>";
    }

    /**
     * @access protected 模板引擎for中的应用
     * @param string $expression for的循环语句
     * @return bool
     * */
    protected function compileFor($expression)
    {
        return "<?php for{$expression}: ?>";
    }

    /**
     * @access protected 模板引擎endfor中的应用
     * @param string $expression 空
     * @return bool
     * */
    protected function compileEndfor($expression)
    {
        return "<?php endfor; ?>";
    }

    /**
     * @access protected 模板引擎foreach中的应用
     * @param string $expression foreach的循环语句
     * @return bool
     * */
    protected function compileForeach($expression)
    {
        return "<?php foreach${expression}: ?>";
    }

    /**
     * @access protected 模板引擎endforeach中的应用
     * @param string $expression 无
     * @return bool
     * */
    protected function compileEndforeach($expression)
    {
        return "<?php endforeach; ?>";
    }

    /**
     * @access protected 模板引擎while中的应用
     * @param string $expression while的循环语句
     * @return bool
     * */
    protected function compileWhile($expression)
    {
        return "<?php while{$expression}: ?>";
    }

    /**
     * @access protected 模板引擎endwhile中的应用
     * @param string $expression 空
     * @return bool
     * */
    protected function compileEndwhile($expression)
    {
        return "<?php endwhile; ?>";
    }

    /**
     * @access protected 模板引擎continue中的应用
     * @param string $expression 空
     * @return bool
     * */
    protected function compileContinue($expression)
    {
        return "<?php continue; ?>";
    }

    /**
     * @access protected 模板引擎break中的应用
     * @param string $expression 空
     * @return bool
     * */
    protected function compileBreak($expression)
    {
        return "<?php break; ?>";
    }
}