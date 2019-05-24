(function($){

    if ($.pdgrid === undefined) $.pdgrid = {
            getUrlParameter: function(url, sParam) {
                var sPageURL = decodeURIComponent(url),
                    sURLVariables = sPageURL.split('&'),
                    sParameterName,
                    i;

                for (i = 0; i < sURLVariables.length; i++) {
                    var s = sURLVariables[i];
                    var p = s.lastIndexOf('?');
                    if (p >= 0) {
                        s = s.substring(p+1);
                    };

                    sParameterName = s.split('=');

                    if (sParameterName[0] === sParam) {
                        return sParameterName[1] === undefined ? true : sParameterName[1];
                    }
                }
            },

            getUrlParamStr: function(url, sParam) {
                var r = this.getUrlParameter(url, sParam);
                if (r === undefined || r === true) return '';
                return r;
            },

            appendUrlParams: function(url, params) {
                var sep = '&';
                if (url.indexOf('?') == -1) sep = '?';
                for (paramName in params) {
                    if (params[paramName] !== undefined /*&& params[paramName] !== ''*/) {
                        url += sep + paramName + '=' + encodeURIComponent(params[paramName]);
                        sep = '&';
                    }
                }
                return url;
            },

            $findGrid: function(el) {
                var $el = (el instanceof jQuery) ? el : $(el);

                var $grid = $el.filter('table.pdgrid');
                if ($grid.length) return $grid.first();

                $grid = $el.parents('table.pdgrid');
                if ($grid.length) return $grid.first();

                $grid = $el.find('table.pdgrid');
                return $grid.first();
            },

            getGridId: function($grid) {
                var id = $grid.attr('id');
                var idPrefix = 'pdgrid_';
                if (id.indexOf(idPrefix) == 0) id = id.slice(idPrefix.length);
                return id;
            },

            confirmDelete: function(el) {
                var $tr = $(el).parents('tr');
                if ($tr.length == 0) return false;
                $tr = $tr.first();

                var $grid = this.$findGrid(el);
                if ($grid.length == 0) return;

                $grid.parent().parent().find('.alert-success.alert-dismissible').remove();

                $tr.css('background-color', '#FAA');
                var rowid = $tr.attr('data-rowid');

                var Delete = function() {
                    var deleteUrl = $grid.attr('data-delete-url');
                    var ajaxDeleteUrl = $grid.attr('data-delete-ajax-url');
                    if (deleteUrl) {
                        deleteUrl = deleteUrl.replace('{id}', rowid);
                        deleteUrl =  deleteUrl.replace('{returnUrl}', encodeURIComponent($grid.attr('data-current-url')));

                        if (ajaxDeleteUrl) {
                            ajaxDeleteUrl = ajaxDeleteUrl.replace('{id}', rowid);
                        }
                        $.pdgrid.load($grid, {url: deleteUrl, ajax: ajaxDeleteUrl, setCurrent: false});
                    }
                }

                var Cancel = function() {
                    $tr.css('background-color', '');
                }

                eModal.alert({
                    async: true,
                    buttons: [{
                        close: true,
                        click: Delete,
                        text: 'Удалить',
                        style: 'danger'
                    }, {
                        close: true,
                        click: Cancel,
                        text: 'Отмена',
                        style: 'default'
                    }],
                    message: 'Вы действительно хотите удалить выделенный элемент?',
                    onHide: Cancel,
                    title: 'Подтвердите удаление'
                });
                return false;
            },

            confirmDeleteGroup: function(el) {
                var $tr = $(el).parents('tr');
                if ($tr.length == 0) return false;
                $tr = $tr.first();

                var $grid = this.$findGrid(el);
                if ($grid.length == 0) return;

                $grid.parent().parent().find('.alert-success.alert-dismissible').remove();

                $tr.addClass('mark-delete');
                var groupid = $tr.attr('data-groupid');

                var Delete = function() {
                    var deleteUrl = $grid.attr('data-deletegroup-url');
                    var ajaxDeleteUrl = $grid.attr('data-deletegroup-ajax-url');
                    if (deleteUrl) {
                        deleteUrl = deleteUrl.replace('{id}', groupid);
                        deleteUrl =  deleteUrl.replace('{returnUrl}', encodeURIComponent($grid.attr('data-current-url')));

                        if (ajaxDeleteUrl) {
                            ajaxDeleteUrl = ajaxDeleteUrl.replace('{id}', groupid);
                        }
                        $.pdgrid.load($grid, {url: deleteUrl, ajax: ajaxDeleteUrl, setCurrent: false});
                    }
                }

                var Cancel = function() {
                    $tr.removeClass('mark-delete');
                }

                eModal.alert({
                    async: true,
                    buttons: [{
                        close: true,
                        click: Delete,
                        text: 'Удалить',
                        style: 'danger'
                    }, {
                        close: true,
                        click: Cancel,
                        text: 'Отмена',
                        style: 'default'
                    }],
                    message: 'Вы действительно хотите удалить выделенную группу и все содержимое?',
                    onHide: Cancel,
                    title: 'Подтвердите удаление'
                });
                return false;
            },

            registerAjaxRequest: function(gridId, url) {
                if (this.ajaxLoadStatus[gridId] === undefined) this.ajaxLoadStatus[gridId] = {};
                var reqid = url;
                var i = 1;
                while(this.ajaxLoadStatus[gridId][reqid] !== undefined) {
                    reqid = url + i;
                    i++;
                }
                this.ajaxLoadStatus[gridId][reqid] = true;
                this.activeAjaxRequest[gridId] = reqid;
                return reqid;
            },

            ajaxLoadStatus: {},

            activeAjaxRequest: {},

            successMsg: false,

            showSuccessMessage: function(el, msg) {
                var $grid = this.$findGrid(el);
                $grid.parent().before('<div class="alert alert-success alert-dismissible fade in" role="alert">' +
                    '<button type="button" class="close" data-dismiss="alert" aria-label="Скрыть">' +
                    '<span aria-hidden="true">×</span></button> Успешно: ' + msg + '</div>');
            },

            reload: function(el) {
                var $grid = this.$findGrid(el);
                var url = {
                    url: $grid.attr('data-current-url'),
                    ajax: $grid.attr('data-current-url-ajax')
                };
                this.load(el, url);
            },

            load: function(el, url) {
                this.hideSelectPageForm();
                var $grid = this.$findGrid(el);
                if ($grid.length == 0) return;
                var gridId = $.pdgrid.getGridId($grid);



                if (url['ajax'] !== undefined && url['ajax'] != '') {
                    if (this.activeAjaxRequest[gridId]) {
                        var activeReqId = this.activeAjaxRequest[gridId]
                        if (this.ajaxLoadStatus[gridId][activeReqId]) {
                            this.ajaxLoadStatus[gridId][activeReqId] = false;
                            $grid.find('tbody').LoadingOverlay("hide");
                        }
                    }
                    $grid.find('tbody').LoadingOverlay("show");

                    var reqid = this.registerAjaxRequest(gridId, url['ajax']);
                    var errMsg = '';
                    $.ajax({
                        url: url['ajax'],
                        data: url['data'],
                        dataType: 'json',
                        success: function(data, status, xhr) {
                            var reqstatus = $.pdgrid.ajaxLoadStatus[gridId][reqid];
                            if (reqstatus == false || reqstatus === undefined) return;
                            if (data.error) {
                                errMsg = data.error;
                            } else if (data.body) {
                                $grid.find('thead').html(data.head);
                                $grid.find('tbody').html(data.body);
                                $grid.find('tfoot td').html('Отображены записи с '+data.first+
                                    ' по ' + data.last + '. Всего записей: ' + data.total);

                                $('#pdgrid_'+gridId+'_paging').replaceWith(data.paging);

                                var $expBtn = $('.pdgrid-'+gridId+'-export');
                                if ($expBtn.length > 0) {
                                    $expBtn.each(function() {
                                        var expUrl = $(this).attr('data-url')
                                            .replace('{page}', data.page);
                                        var currentUrl = $grid.attr('data-current-url');
                                        var urlParams = {
                                            sort: $.pdgrid.getUrlParameter(currentUrl, 'sort'),
                                            filter: $.pdgrid.getUrlParameter(currentUrl, 'filter')
                                        };
                                        expUrl = $.pdgrid.appendUrlParams(expUrl, urlParams);
                                        $(this).attr('href', expUrl);
                                    });
                                }

                                if (data.script) {
                                    $(data.script).appendTo($('body'));
                                }

                            } else if (data.reload) {
                                if (data.message) {
                                    $.pdgrid.showSuccessMessage($grid, data.message);
                                    $.pdgrid.reload($grid);
                                }
                            } else {
                                errMsg = 'Неверный ответ сервера';
                            }
                        },

                        error: function (xhr, status, err) {
                            window.setTimeout(function() {
                                eModal.alert(err, 'Ошибка: ' + status);
                            }, 200);
                        },

                        complete: function(xhr, status) {
                            var reqstatus = $.pdgrid.ajaxLoadStatus[gridId][reqid];
                            if (reqstatus) {
                                $grid.find('tbody').LoadingOverlay("hide");
                            }
                            delete $.pdgrid.ajaxLoadStatus[gridId][reqid];
                            if (errMsg) {
                                window.setTimeout(function() {
                                    eModal.alert(errMsg, 'Ошибка!');
                                }, 500);

                            }
                        }

                    });
                    this.setCurrentUrl(gridId, url);
                } else {
                    $grid.find('tbody').LoadingOverlay("show");
                    var urlStr = url['url'];
                    if (url['data']) {
                        urlStr = this.appendUrlParams(urlStr, url['data']);
                    }
                    location.href = urlStr;
                }
            },

            setCurrentUrl: function(gridId, url) {
                if (url.setCurrent === false) return;
                var $grid = this.$gridById(gridId);
                $grid.attr('data-current-url', url['url']);
                $grid.attr('data-current-url-ajax', url['ajax']);
                var $updReturnUrl = $('.pdgrid-'+gridId+'-returnUrl');
                if ($updReturnUrl.length > 0) {
                    $updReturnUrl.attr('href', $updReturnUrl.attr('data-url').replace('{returnUrl}', encodeURIComponent(url['url'])));
                }
                if ($grid.attr('data-ajax-set-url')) {
                    if (window.history.replaceState !== undefined) {
                        var _ = undefined;
                        window.history.replaceState(_, _, url['url']);
                    }
                }
            },

            makeUrl: function($grid, params) {
                var url = $grid.attr('data-url');
                var ajaxUrl = $grid.attr('data-ajax');
                var currentUrl = $grid.attr('data-current-url');
                var urlParams = {
                    sort: this.getUrlParameter(currentUrl, 'sort'),
                    filter: this.getUrlParameter(currentUrl, 'filter'),
                    page: this.getUrlParameter(currentUrl, 'page')
                };
                $.extend(urlParams, params);
                return {
                    'url': this.appendUrlParams(url, urlParams),
                    'ajax': (ajaxUrl ? this.appendUrlParams(ajaxUrl, urlParams): '')
                };
            },

            $gridById: function(gridId) {
                return $("#pdgrid_" + gridId);
            },

            gotoPage: function(gridId, pageNum) {
                var $grid = this.$gridById(gridId);
                this.load($grid, this.makeUrl($grid, { page: pageNum }));
                return false;
            },

            selectPageForm: null,

            hideSelectPageForm: function() {
                if (this.selectPageForm != null) {
                    var li2 = this.selectPageForm.parents('li').get(0);
                    $(li2).find('a').show();
                    this.selectPageForm.remove();
                    this.selectPageForm = null;
                }
            },

            selectPage: function(gridId, el, pageNum, maxPage) {
                this.hideSelectPageForm();
                var li = $(el).parents('li').get(0);
                $(li).find('a').hide();
                this.selectPageForm = $('<div class="pdgrid-paging-select-page-form input-group">' +
                    '<input type="number" name="page" min="1" max="'+maxPage+'" class="form-control input-sm">'+
                    '<span class="input-group-btn"><button class="btn btn-default btn-sm" type="button">'+
                    '<i class="glyphicon glyphicon-triangle-right"></i></button>' +
                    '</span></div>');
                //var $grid = this.$gridById(gridId);
                //var url = this.makeUrl($grid, {page: false});

                $(li).append(this.selectPageForm);
                var input = this.selectPageForm.find('input').val(pageNum).get(0);
                input.select();
                $(input).on('keyup', function(event) {
                    if (event.keyCode == 13) {
                        $.pdgrid.gotoPage(gridId, $(this).val());
                        $.pdgrid.hideSelectPageForm();
                    }
                });
                this.selectPageForm.find('button').on('click', function() {
                    $.pdgrid.gotoPage(gridId, $(input).val());
                    $.pdgrid.hideSelectPageForm();
                });
                return false;
            },

            sort: function(el, colId) {
                var $grid = this.$findGrid(el);
                var currentUrl = $grid.attr('data-current-url');
                var sortStr = this.getUrlParameter(currentUrl, 'sort');
                if (sortStr === undefined || sortStr === true) sortStr = $grid.attr('data-default-sort');
                if (sortStr === undefined) sortStr = '';
                var multisort = ($grid.attr('data-multisort') == 'true');

                var sortCols = {},
                    sortOrder = [];

                if (sortStr !== '') {
                    var sortArr = sortStr.split(';');
                    for (var i in sortArr) {
                        var p = sortArr[i].split(':');
                        var name = '';
                        var dir = '';
                        if (p.length > 1) {
                            name = p[0];
                            dir = p[1];
                        } else {
                            name = p[0];
                            dir = 'asc';
                        }
                        sortCols[name] = dir;
                        sortOrder[i] = name;
                    }
                }

                var makeSortParamStr = function(colName, apply = false) {
                    var sortColsCopy = {}; $.extend(sortColsCopy, sortCols);
                    var sortOrderCopy = []; $.extend(sortOrderCopy, sortOrder);

                    var newDir = '';
                    if (sortColsCopy[colName] !== undefined) {
                        if (sortColsCopy[colName] == 'asc') {
                            newDir = 'desc';
                        }
                    } else {
                        newDir = 'asc';
                    }

                    if (multisort) {
                        if (newDir == '') {
                            delete sortColsCopy[colName];
                        } else {
                            sortColsCopy[colName] = newDir;
                        }
                        var pos = $.inArray(colName, sortOrderCopy);

                        if (newDir == '') {
                            sortOrderCopy.splice(pos, 1);
                        } else {
                            if (pos > 0) {
                                sortOrderCopy.splice(pos, 1);
                            }
                            if (pos == -1 || pos > 0) {
                                sortOrderCopy.splice(0, 0, colName);
                            }
                        }
                    } else {
                        sortColsCopy = {};
                        sortOrderCopy = [];
                        if (newDir != '') {
                            sortColsCopy[colName] = newDir;
                            sortOrderCopy[0] = colName;
                        }
                    }

                    var res = '';
                    for (var j in sortOrderCopy) {
                        var curColName = sortOrderCopy[j];
                        if (res != '') res += ';';
                        res += curColName + ':' + sortColsCopy[curColName];
                    }

                    if (apply) {
                        sortCols = sortColsCopy;
                        sortOrder = sortOrderCopy;
                    }

                    return res;
                }

                sortStr = makeSortParamStr(colId, true);

                $grid.find('.pdgrid_sort').each(function()
                {
                    var colId = $(this).attr('data-column-id');
                    var sortStr = makeSortParamStr(colId);
                    var url = $.pdgrid.makeUrl($grid, {sort: sortStr });
                    $(this).attr('href', url['url']);

                    if (sortCols[colId] === undefined) {
                        $(this).find('sub').last().remove();
                        $(this).find('i.glyphicon').last().remove();
                    } else {
                        var $glyph = $(this).find('i.glyphicon');
                        if ($glyph.length > 0) {
                            $glyph = $glyph.last();
                        } else {
                            $(this).append(" ");
                            $glyph = $('<i>').appendTo(this);
                        }
                        $glyph.attr('class', 'glyphicon');
                        if (sortCols[colId] == 'asc') {
                            $glyph.addClass('glyphicon-triangle-top');
                        } else {
                            $glyph.addClass('glyphicon-triangle-bottom');
                        }

                        if (!multisort || sortOrder.length <= 1) {
                            $(this).find('sub').last().remove();
                        } else {
                            var $subOrd = $(this).find('sub');
                            if ($subOrd.length > 0) {
                                $subOrd = $subOrd.last();
                            } else {
                                $subOrd = $('<sub>').appendTo(this);
                            }

                            $subOrd.text($.inArray(colId, sortOrder) + 1);
                        }

                    }
                });

                this.load($grid, this.makeUrl($grid, { sort: sortStr }));
                return false;
            },

            clearFilters: function(el, filterName) {
                var $grid = this.$findGrid(el);
                $grid.find('[data-filter]').each(function() {
                    var $f = $(this);
                    if (filterName && $f.attr('data-filter') != filterName) return;
                    if ($f.is('input[type="checkbox"]')) {
                        $f.prop('checked', false);
                    } else if ($f.is('select')) {
                        if ($f.prop('multiple')) {
                            $f.val([]); //find('option').prop('selected', false);
                        } else {
                            $f.prop('selectedIndex', 0);
                        }
                    } else if ($f.is('input') || $f.is('textarea')) {
                        $f.val('');
                    } else {
                        $f.text($f.attr('data-empty'));
                    }
                });
                this.filter(el);
                return false;
            },

            filter: function(el) {
                var $grid = this.$findGrid(el);
                var filterStr = '';
                $grid.find('[data-filter]').each(function() {
                    var $f = $(this);
                    var v = $f.val();

                    if (v) {
                        if (filterStr !== '') filterStr += ';';
                        filterStr += $f.attr('data-filter') + ':';
                        if ($.isNumeric(v)) {
                            filterStr += v
                        } else if ($.isArray(v)) {
                            filterStr += v.join(',');
                        } else {
                            filterStr += "'" + v.replace(/'/g, "\\'") + "'";
                        }
                    }
                });
                this.load($grid, this.makeUrl($grid, { filter: filterStr }));
                return false;
            }


    };

    $(function() {
        $(document).on("click", ".pdgrid .filters .form-control-clear", function() {
            $.pdgrid.clearFilters(this, $(this).attr("filterId"));
        })
    });

    /*window.addEventListener('popstate', function(e) {
        if ($.pdgrid.ajaxHistory[e.state.path] !== undefined) {
            var state = $.pdgrid.ajaxHistory[e.state.path];
            $.pdgrid.loadAjax(e.state., e.state.path, state.ajaxUrl);
        } else {
            location.href = e.state.path;
        }
    }, false);*/
})(jQuery);