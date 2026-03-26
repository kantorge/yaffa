import select2 from 'select2';
import { loadSelect2Language } from '@/shared/lib/i18n/select2';

let isSelect2Initialized = false;
let isSelect2SearchPlaceholderPatched = false;

function getDefaultSearchPlaceholder() {
    if (typeof window.__ === 'function') {
        return window.__('Type to search...');
    }

    return 'Type to search...';
}

function patchSelect2SearchInputPlaceholder() {
    if (isSelect2SearchPlaceholderPatched) {
        return;
    }

    const $ = window.jQuery;
    if (!$?.fn?.select2?.amd) {
        return;
    }

    const Defaults = $.fn.select2.amd.require('select2/defaults');
    const SearchDropdown = $.fn.select2.amd.require('select2/dropdown/search');

    if (!Defaults?.defaults || !SearchDropdown?.prototype?.render) {
        return;
    }

    $.extend(Defaults.defaults, {
        searchInputPlaceholder: getDefaultSearchPlaceholder(),
    });

    const originalRenderSearchDropdown = SearchDropdown.prototype.render;

    SearchDropdown.prototype.render = function () {
        const renderedSearchDropdown = originalRenderSearchDropdown.apply(this, arguments);
        this.$search.attr('placeholder', this.options.get('searchInputPlaceholder'));

        return renderedSearchDropdown;
    };

    isSelect2SearchPlaceholderPatched = true;
}

function ensureSelect2Initialized() {
    if (isSelect2Initialized) {
        return;
    }

    select2();
    isSelect2Initialized = true;
}

export function initializeSelect2(lang = window.YAFFA?.userSettings?.language || window.YAFFA?.language || 'en') {
    ensureSelect2Initialized();
    patchSelect2SearchInputPlaceholder();

    return loadSelect2Language(lang);
}