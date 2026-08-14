/**
 * 首页交互：资源筛选、搜索、详情侧栏
 * 详情通过 fragment 接口异步加载，URL 与浏览器历史保持同步
 */

(function () {
    'use strict';

    var grid = document.getElementById('resource-grid');
    if (!grid) {
        return;
    }

    var cards = Array.prototype.slice.call(grid.querySelectorAll('.card'));
    var filterBtns = Array.prototype.slice.call(document.querySelectorAll('.filter-btn:not(.category-filter)'));
    var categoryFilterBtns = Array.prototype.slice.call(document.querySelectorAll('.filter-btn.category-filter'));
    var categoryGroups = Array.prototype.slice.call(document.querySelectorAll('.category-group'));
    var searchInput = document.getElementById('search-input');
    var searchClear = document.getElementById('search-clear');
    var emptyState = document.getElementById('empty-state');
    var resultCount = document.getElementById('result-count');
    var announcer = document.getElementById('announcer');

    var sheet = document.getElementById('sheet');
    var backdrop = document.getElementById('sheet-backdrop');
    var sheetTitle = document.getElementById('sheet-title');
    var sheetBody = document.getElementById('sheet-body');
    var sheetClose = document.getElementById('sheet-close');

    var items = cards.map(function (el) {
        var platforms = [];
        try {
            platforms = JSON.parse(el.dataset.platforms || '[]');
        } catch (e) {
            platforms = [];
        }
        return {
            el: el,
            platforms: platforms,
            category: el.dataset.category || '未分类',
            search: el.dataset.search || ''
        };
    });

    /* ---------- 筛选与搜索 ---------- */

    var currentPlatform = 'all';
    var currentCategory = 'all';
    var currentQuery = '';

    function applyFilter() {
        var visible = 0;
        var visibleByGroup = {};

        items.forEach(function (item) {
            var matchPlatform = currentPlatform === 'all' || item.platforms.indexOf(currentPlatform) !== -1;
            var matchCategory = currentCategory === 'all' || item.category === currentCategory;
            var matchQuery = currentQuery === '' || item.search.indexOf(currentQuery) !== -1;
            var match = matchPlatform && matchCategory && matchQuery;
            item.el.hidden = !match;
            if (match) {
                visible++;
                visibleByGroup[item.category] = (visibleByGroup[item.category] || 0) + 1;
            }
        });

        // 分组可见性管理
        categoryGroups.forEach(function (group) {
            var groupCategory = group.dataset.categoryGroup || '';
            var hasVisible = visibleByGroup[groupCategory] > 0;
            group.hidden = !hasVisible;
        });

        if (emptyState) {
            emptyState.hidden = visible !== 0;
        }
        if (resultCount) {
            resultCount.textContent = visible + ' 个资源';
        }
        if (announcer) {
            announcer.textContent = visible === 0
                ? '没有匹配的资源'
                : '共显示 ' + visible + ' 个资源';
        }
    }

    filterBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            filterBtns.forEach(function (b) {
                b.classList.remove('active');
                b.setAttribute('aria-pressed', 'false');
            });
            btn.classList.add('active');
            btn.setAttribute('aria-pressed', 'true');
            currentPlatform = btn.dataset.platform;
            applyFilter();
        });
    });

    categoryFilterBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            categoryFilterBtns.forEach(function (b) {
                b.classList.remove('active');
                b.setAttribute('aria-pressed', 'false');
            });
            btn.classList.add('active');
            btn.setAttribute('aria-pressed', 'true');
            currentCategory = btn.dataset.category;
            applyFilter();
        });
    });

    if (searchInput) {
        var debounce = null;
        searchInput.addEventListener('input', function () {
            if (searchClear) {
                searchClear.hidden = searchInput.value === '';
            }
            clearTimeout(debounce);
            debounce = setTimeout(function () {
                currentQuery = searchInput.value.trim().toLowerCase();
                applyFilter();
            }, 140);
        });

        searchInput.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && searchInput.value !== '') {
                e.stopPropagation();
                clearSearch();
            }
        });
    }

    function clearSearch() {
        if (!searchInput) {
            return;
        }
        searchInput.value = '';
        currentQuery = '';
        if (searchClear) {
            searchClear.hidden = true;
        }
        applyFilter();
        searchInput.focus();
    }

    if (searchClear) {
        searchClear.addEventListener('click', clearSearch);
    }

    /* ---------- 详情侧栏 ---------- */

    if (!sheet || !backdrop || !sheetBody) {
        return;
    }

    var lastFocused = null;
    var currentKey = null;
    var cache = {};
    var requestToken = 0;

    var FOCUSABLE = 'a[href], button:not([disabled]), input, select, textarea, summary, [tabindex]:not([tabindex="-1"])';

    function skeleton() {
        return '<div class="skeleton-line" style="width:42%"></div>' +
            '<div class="skeleton-line" style="width:88%"></div>' +
            '<div class="skeleton-line" style="width:70%"></div>' +
            '<div class="skeleton-block"></div>' +
            '<div class="skeleton-block"></div>';
    }

    function errorState(message) {
        return '<div class="alert alert-danger"><span>' + message + '</span></div>';
    }

    function openSheet(owner, repo, name, pushState) {
        lastFocused = document.activeElement;
        currentKey = owner + '/' + repo;

        sheetTitle.textContent = name;
        sheet.hidden = false;
        backdrop.hidden = false;

        // 强制回流后再加类，确保过渡动画生效
        void sheet.offsetWidth;
        sheet.classList.add('open');
        backdrop.classList.add('open');
        document.body.classList.add('sheet-open');

        sheetClose.focus();

        if (pushState) {
            var url = 'index.php?owner=' + encodeURIComponent(owner) + '&repo=' + encodeURIComponent(repo);
            history.pushState({ owner: owner, repo: repo, name: name }, '', url);
        }

        loadDetail(owner, repo);
    }

    function loadDetail(owner, repo) {
        var key = owner + '/' + repo;

        if (cache[key]) {
            sheetBody.innerHTML = cache[key];
            sheetBody.scrollTop = 0;
            return;
        }

        var token = ++requestToken;
        sheetBody.innerHTML = skeleton();
        sheetBody.scrollTop = 0;

        var url = 'index.php?owner=' + encodeURIComponent(owner) +
            '&repo=' + encodeURIComponent(repo) + '&fragment=1';

        fetch(url, { headers: { 'X-Requested-With': 'fetch' } })
            .then(function (res) {
                if (!res.ok) {
                    throw new Error('HTTP ' + res.status);
                }
                return res.text();
            })
            .then(function (html) {
                if (token !== requestToken || currentKey !== key) {
                    return;
                }
                cache[key] = html;
                sheetBody.innerHTML = html;
                sheetBody.scrollTop = 0;
            })
            .catch(function () {
                if (token !== requestToken || currentKey !== key) {
                    return;
                }
                sheetBody.innerHTML = errorState('加载失败，请稍后重试。');
            });
    }

    function closeSheet(pushState) {
        if (sheet.hidden) {
            return;
        }

        sheet.classList.remove('open');
        backdrop.classList.remove('open');
        document.body.classList.remove('sheet-open');
        currentKey = null;
        requestToken++;

        var finish = function () {
            sheet.hidden = true;
            backdrop.hidden = true;
            sheetBody.innerHTML = '';
        };

        var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (reduced) {
            finish();
        } else {
            setTimeout(finish, 320);
        }

        if (pushState) {
            history.pushState({}, '', 'index.php');
        }

        if (lastFocused && document.contains(lastFocused)) {
            lastFocused.focus();
        }
        lastFocused = null;
    }

    cards.forEach(function (card) {
        card.addEventListener('click', function (e) {
            // 保留新窗口打开等原生行为
            if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0) {
                return;
            }
            e.preventDefault();
            openSheet(card.dataset.owner, card.dataset.repo, card.dataset.name, true);
        });
    });

    sheetClose.addEventListener('click', function () {
        closeSheet(true);
    });

    backdrop.addEventListener('click', function () {
        closeSheet(true);
    });

    document.addEventListener('keydown', function (e) {
        if (sheet.hidden) {
            return;
        }

        if (e.key === 'Escape') {
            e.preventDefault();
            closeSheet(true);
            return;
        }

        // 焦点锁定在侧栏内
        if (e.key === 'Tab') {
            var focusable = Array.prototype.slice.call(sheet.querySelectorAll(FOCUSABLE))
                .filter(function (el) {
                    return el.offsetParent !== null;
                });
            if (focusable.length === 0) {
                return;
            }
            var first = focusable[0];
            var last = focusable[focusable.length - 1];

            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        }
    });

    window.addEventListener('popstate', function (e) {
        var state = e.state;
        if (state && state.owner && state.repo) {
            openSheet(state.owner, state.repo, state.name || (state.owner + '/' + state.repo), false);
        } else {
            closeSheet(false);
        }
    });

    // 深链进入：直接展开对应资源
    (function initFromUrl() {
        var params = new URLSearchParams(window.location.search);
        var owner = params.get('owner');
        var repo = params.get('repo');
        if (!owner || !repo) {
            return;
        }
        var match = cards.filter(function (card) {
            return card.dataset.owner === owner && card.dataset.repo === repo;
        })[0];
        if (match) {
            openSheet(owner, repo, match.dataset.name, false);
        }
    })();

    /* ---------- 自定义下载 ---------- */

    var customRepoInput = document.getElementById('custom-repo-input');
    var customRepoSubmit = document.getElementById('custom-repo-submit');
    var customRepoError = document.getElementById('custom-repo-error');

    if (customRepoInput && customRepoSubmit && customRepoError) {
        function parseRepoInput(input) {
            var trimmed = input.trim();
            var match = trimmed.match(/^(?:https?:\/\/github\.com\/)?([A-Za-z0-9._-]+)\/([A-Za-z0-9._-]+?)(?:\.git)?$/);
            if (match) {
                return { owner: match[1], repo: match[2] };
            }
            return null;
        }

        function submitCustomRepo() {
            var input = customRepoInput.value;
            var parsed = parseRepoInput(input);

            if (!parsed) {
                customRepoError.textContent = '格式错误，请输入 owner/repo 或完整 GitHub URL';
                customRepoInput.focus();
                return;
            }

            customRepoError.textContent = '';
            customRepoInput.value = '';
            openSheet(parsed.owner, parsed.repo, parsed.owner + '/' + parsed.repo, true);
        }

        customRepoSubmit.addEventListener('click', submitCustomRepo);

        customRepoInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                submitCustomRepo();
            }
        });

        customRepoInput.addEventListener('input', function () {
            if (customRepoError.textContent !== '') {
                customRepoError.textContent = '';
            }
        });
    }
})();
