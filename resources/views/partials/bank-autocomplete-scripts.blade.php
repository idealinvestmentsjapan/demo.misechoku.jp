{{-- 金融機関・支店の datalist 補完（/api/bank-lookup + teraren フォールバック）および口座番号の数字正規化 --}}
<script>
(function () {
    function normalizeAccountNumber(value) {
        return String(value || '').replace(/\D+/g, '').slice(0, 8);
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-account-number-input]').forEach(function (input) {
            var syncNumber = function () {
                input.value = normalizeAccountNumber(input.value);
            };

            input.addEventListener('input', syncNumber);
            input.addEventListener('blur', syncNumber);
            syncNumber();
        });
    });
})();
</script>
<script>
(function () {
    function debounce(fn, wait) {
        var timer = null;

        return function () {
            var args = arguments;
            var context = this;
            clearTimeout(timer);
            timer = window.setTimeout(function () {
                fn.apply(context, args);
            }, wait);
        };
    }

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function setOptions(listEl, items, formatLabel) {
        if (!listEl) {
            return;
        }

        listEl.innerHTML = items.map(function (item) {
            var label = formatLabel ? formatLabel(item) : '';

            return '<option value="' + escapeHtml(item.name) + '" label="' + escapeHtml(label) + '"></option>';
        }).join('');
    }

    function normalize(value) {
        return String(value || '').trim();
    }

    function fetchJson(url) {
        return fetch(url, {
            headers: {
                Accept: 'application/json'
            }
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('Request failed');
            }

            return response.json();
        });
    }

    // teraren の生レスポンスでは name が略称（例: "みずほ"）、normalize.name が
    // 正式名（"みずほ銀行"）。datalist の候補として選ばれた値をそのまま bank_name
    // として保存するため、正式名（normalize.name）を優先する。サーバ API は
    // BankLookupService::mapBank 側で既に normalize.name を name として返してくる。
    function mapBankItem(item) {
        var normalizedName = (item.normalize && item.normalize.name) || '';
        return {
            code: String(item.code || ''),
            name: String(normalizedName || item.name || ''),
            short_name: String(item.short_name || item.name || ''),
            kana: String(item.kana || (item.normalize && item.normalize.kana) || ''),
            hira: String(item.hira || (item.normalize && item.normalize.hira) || '')
        };
    }

    function mapBranchItem(item, bankCode) {
        var normalizedName = (item.normalize && item.normalize.name) || '';
        return {
            bank_code: String(item.bank_code || bankCode || ''),
            code: String(item.code || ''),
            name: String(normalizedName || item.name || ''),
            short_name: String(item.short_name || item.name || ''),
            kana: String(item.kana || (item.normalize && item.normalize.kana) || ''),
            hira: String(item.hira || (item.normalize && item.normalize.hira) || '')
        };
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('form[data-bank-autocomplete]').forEach(function (form) {
            var bankInput = form.querySelector('[data-bank-name-input]');
            var bankCodeInput = form.querySelector('[data-bank-code-input]');
            var bankList = form.querySelector('[data-bank-list]');
            var branchInput = form.querySelector('[data-branch-name-input]');
            var branchCodeInput = form.querySelector('[data-branch-code-input]');
            var branchList = form.querySelector('[data-branch-list]');

            if (!bankInput || !bankCodeInput || !bankList || !branchInput || !branchCodeInput || !branchList) {
                return;
            }

            var bankMap = new Map();
            var branchCache = new Map();
            var branchMap = new Map();

            function syncSelectedBank() {
                var key = normalize(bankInput.value);
                var selected = bankMap.get(key);

                bankCodeInput.value = selected ? selected.code : '';
                branchCodeInput.value = '';

                if (!selected) {
                    branchMap.clear();
                    branchList.innerHTML = '';
                }
            }

            function syncSelectedBranch() {
                var key = normalize(branchInput.value);
                var selected = branchMap.get(key);

                branchCodeInput.value = selected ? selected.code : '';
            }

            // Client-side cache of the full teraren bank list, so the fallback
            // path runs one XHR per session instead of one per keystroke.
            var teraerenBanksPromise = null;
            function loadTerarenBanks() {
                if (teraerenBanksPromise) return teraerenBanksPromise;
                teraerenBanksPromise = fetchJson('https://bank.teraren.com/banks.json')
                    .then(function (items) {
                        return Array.isArray(items) ? items.map(mapBankItem) : [];
                    })
                    .catch(function () {
                        teraerenBanksPromise = null; // allow retry next time
                        return [];
                    });
                return teraerenBanksPromise;
            }

            function applyBankItems(items, query) {
                var needle = normalize(query).toLowerCase();
                var filtered = needle === ''
                    ? items.slice(0, 20)
                    : items.filter(function (item) {
                          return [item.code, item.name, item.short_name, item.kana, item.hira].some(function (value) {
                              return normalize(value).toLowerCase().indexOf(needle) !== -1;
                          });
                      }).slice(0, 20);

                bankMap.clear();
                filtered.forEach(function (item) {
                    bankMap.set(normalize(item.name), item);
                });
                setOptions(bankList, filtered, function (item) {
                    return item.code;
                });
                syncSelectedBank();

                if (normalize(branchInput.value) !== '' && bankCodeInput.value) {
                    searchBranches();
                }
                return filtered.length;
            }

            var searchBanks = debounce(function () {
                var query = normalize(bankInput.value);

                if (query.length < 1) {
                    bankMap.clear();
                    bankList.innerHTML = '';
                    bankCodeInput.value = '';
                    return;
                }

                // Primary: server API (fast, cached). Fallback: teraren directly
                // (public, CORS-allowed) — used whenever the primary throws OR
                // returns an empty list (which happens when the server's upstream
                // fetch previously failed and cached nothing / when the query
                // predates the server cache warm-up).
                fetchJson('/api/bank-lookup/banks?q=' + encodeURIComponent(query))
                    .then(function (data) {
                        var items = Array.isArray(data.items) ? data.items : [];
                        if (items.length > 0) {
                            applyBankItems(items, query);
                            return;
                        }
                        return loadTerarenBanks().then(function (fallbackItems) {
                            applyBankItems(fallbackItems, query);
                        });
                    })
                    .catch(function () {
                        return loadTerarenBanks().then(function (fallbackItems) {
                            applyBankItems(fallbackItems, query);
                        });
                    });
            }, 250);

            function fetchTerarenBranches(bankCode) {
                return fetchJson('https://bank.teraren.com/banks/' + encodeURIComponent(bankCode) + '/branches.json')
                    .then(function (items) {
                        return Array.isArray(items)
                            ? items.map(function (item) { return mapBranchItem(item, bankCode); })
                            : [];
                    })
                    .catch(function () { return []; });
            }

            function loadBranches(bankCode) {
                if (!bankCode) {
                    return Promise.resolve([]);
                }

                if (branchCache.has(bankCode)) {
                    return Promise.resolve(branchCache.get(bankCode));
                }

                return fetchJson('/api/bank-lookup/branches?bank_code=' + encodeURIComponent(bankCode))
                    .then(function (data) {
                        var items = Array.isArray(data.items) ? data.items : [];
                        if (items.length > 0) {
                            branchCache.set(bankCode, items);
                            return items;
                        }
                        return fetchTerarenBranches(bankCode).then(function (mapped) {
                            branchCache.set(bankCode, mapped);
                            return mapped;
                        });
                    })
                    .catch(function () {
                        return fetchTerarenBranches(bankCode).then(function (mapped) {
                            branchCache.set(bankCode, mapped);
                            return mapped;
                        });
                    });
            }

            var searchBranches = debounce(function () {
                var bankCode = normalize(bankCodeInput.value);
                var query = normalize(branchInput.value);

                if (!bankCode) {
                    branchMap.clear();
                    branchList.innerHTML = '';
                    branchCodeInput.value = '';
                    return;
                }

                loadBranches(bankCode)
                    .then(function (items) {
                        var needle = query.toLowerCase();
                        var filtered = items.filter(function (item) {
                            if (query === '') {
                                return true;
                            }

                            return [item.code, item.name, item.short_name, item.kana, item.hira].some(function (value) {
                                return normalize(value).toLowerCase().indexOf(needle) !== -1;
                            });
                        }).slice(0, 30);

                        branchMap.clear();
                        filtered.forEach(function (item) {
                            branchMap.set(normalize(item.name), item);
                        });
                        setOptions(branchList, filtered, function (item) {
                            return item.code;
                        });
                        syncSelectedBranch();
                    })
                    .catch(function () {
                        branchMap.clear();
                        branchList.innerHTML = '';
                    });
            }, 250);

            bankInput.addEventListener('input', function () {
                bankCodeInput.value = '';
                branchInput.value = '';
                branchCodeInput.value = '';
                branchMap.clear();
                branchList.innerHTML = '';
                searchBanks();
            });

            bankInput.addEventListener('change', syncSelectedBank);
            bankInput.addEventListener('blur', syncSelectedBank);

            branchInput.addEventListener('focus', searchBranches);
            branchInput.addEventListener('input', function () {
                branchCodeInput.value = '';
                searchBranches();
            });
            branchInput.addEventListener('change', syncSelectedBranch);
            branchInput.addEventListener('blur', syncSelectedBranch);

            if (normalize(bankInput.value) !== '') {
                searchBanks();
            }
        });
    });
})();
</script>
