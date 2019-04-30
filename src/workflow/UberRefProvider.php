<?php

final class UberRefProvider {
    private $are_custom_refs_enabled;

    function __construct(bool $is_non_tag_ref_enabled) {
        $this->are_custom_refs_enabled = $is_non_tag_ref_enabled;
    }

    public function getBaseRefName($prefix, $id) {
        if ($this->are_custom_refs_enabled) {
            return "refs/{$prefix}/base/{$id}";
        }
        else {
            return "refs/tags/{$prefix}/base/{$id}";
        }
    }

    public function getDiffRefName($prefix, $id) {
        if ($this->are_custom_refs_enabled) {
            return "refs/{$prefix}/diff/{$id}";
        }
        else {
            return "refs/tags/{$prefix}/diff/{$id}";
        }
    }
}