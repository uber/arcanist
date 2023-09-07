<?php

final class ArcanistCommentStyleXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 18;

  public function getLintName() {
    return pht('Comment Style');
  }

  public function process(XHPASTNode $root) {
    foreach ($root->selectTokensOfType('T_COMMENT') as $comment) {
      $value = $comment->getValue();

      if ($value[0] !== '#') {
        continue;
      }

      if (preg_match('/^#\\[\\\\/', $value)) {
        continue;
      }

      $this->raiseLintAtOffset(
        $comment->getOffset(),
        pht(
          'Use `%s` single-line comments, not `%s`.',
          '//',
          '#'),
        '#',
        preg_match('/^#\S/', $value) ? '// ' : '//');
    }
  }

}
