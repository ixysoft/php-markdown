# php-markdown
markdown translater  
**这是一个将基于PHP的markdown文档解析项目,可将md文件解析成显示友好的页面**  
**当前版本支持以下特性**  
1. 普通文本
2. 粗体
3. 斜体
4. 有序列表
5. 无序列表
6. 代码显示
7. 引用
8. 网址
9. 图片显示
10. 已选项
11. 未选项
12. 1-6级标题
13. 支持表格显示
14. 支持行内加粗,斜体,链接,图片,代码

**使用方法:**
```php
<?php
  require 'MD.php'
  
  $md=new MD('test.md');  //加载指定markdown文件
  $md->output();          //将解析内容输出
```
