function debounce(callback, delay = 500) {
    let timeout;

    return (...args) => {
        clearTimeout(timeout);
        timeout = setTimeout(() => {
            callback(...args);
        }, delay)
    }
}

function unique(array) {
    return [...new Set(array)];
}

$.fn.value = function(value = undefined) {
    if(typeof value == 'undefined') {
        if(this.attr('type') == 'date') {
            return new Date(this.val()).toISOString();
        } else if(this.attr('type') == 'time') {
            let [hours, minutes] = this.val().split(':');
            return {hours: hours,minutes: minutes};
        } else if(typeof this.attr('data-rich-editor') != 'undefined') {
            let html  = '<div>'+this.data('editor').html.get()+'</div>';
            let $html = $($.parseHTML(html));
            // Verwijder Froala watermerk
            $html.find('[data-f-id="pbf"]').remove();
            return $html.html();
        } else if(this.attr('contenteditable') == 'true') {
            return this.text();
        } else if(this.hasClass('input-tags')) {
            let $wrapper = this.closest('.input-tags-wrapper');
            let tags   = Object.keys($wrapper.data('tags') || {});
            return tags;
         } else if(this.hasClass('input-search')) {
            return this.attr('data-value') || this.val();
        } else {
            return this.val();
        }
    } else {
        if(this.attr('type') == 'date') {
            let date = (value ? new Date(value) : new Date());
            return this.get(0).value = `${date.getFullYear()}-${addLeadingZero(date.getMonth()+1)}-${addLeadingZero(date.getDate())}`;
        } else if(this.attr('type') == 'time') {
            let date = new Date(value);
            return this.val(`${addLeadingZero(date.getHours())}:${addLeadingZero(date.getMinutes())}:${addLeadingZero(date.getSeconds())}`);
        } else if(typeof this.attr('data-rich-editor') != 'undefined') {
            return this.data('editor').html.set(value);
        } else if(this.attr('contenteditable') == 'true') {
            return this.text(value);
        } else if(this.hasClass('input-search')) {
            return this.attr('data-value', value);
        } else {
            return this.val(value);
        }
    }
}

$.fn.formSerialize = function() {
    let $form  = this;
    let form   = this.get(0);
    let output = {};

    if(form.nodeName.toLowerCase() != 'form') {
        return false;
    }

    $form.find('[name]').each(function() {
        let $input = $(this);

        if($input.get(0).nodeName.toLowerCase() == 'textarea') {
            // Replace newline with br
            output[$input.attr('name')] = $input.value().replace(/\r\n|\r|\n/g, '<br/>');
        } else {
            output[$input.attr('name')] = $input.value();
        }
    })

    return output;
}

$.fn.formReset = function() {
    let $form  = this;
    let form   = this.get(0);

    if(form.nodeName.toLowerCase() != 'form') {
        return false;
    }

    $form.find('[name]').each(function() {
        let $el = $(this);
        if($el.hasClass('input-tags')) {
            $el.setTags({});
        } else if($el.attr('type') == 'time' && $el.attr('value')) {
            $el.val($el.attr('value'));
        } else if($el.attr('type') == 'date') {
            $el.value(null);
        } else if(typeof $el.attr('data-rich-editor') != 'undefined') {
            $el.data('editor').html.set('');
        } else {
            $el.val('');
        }
    })
}

$(document).on('click', function(e) {
    if($(e.target).closest('[data-magistraal="nav-toggler"]').length == 0 && magistraalStorage.get('nav_active')) {
        e.preventDefault();
        magistraal.nav.close();
    }
})

/* ============================ */
/*           Settings           */
/* ============================ */

$(document).on('magistraal.change', '.setting-list-item input', function(e) {
    let $input   = $(this);
    let value    = $input.value();
    let $setting = $input.closest('.setting-list-item');
    let setting  = $setting.attr('data-setting');

    // Add system theme to auto theme to prevent flash when app starts
    if(setting == 'appearance.theme' && value == 'auto') {
        value = (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark_auto' : 'light_auto');
    }

    magistraal.settings.set(setting, value);
})

// If system theme changes
if(window.matchMedia) {
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
        let settings = magistraalPersistentStorage.get('settings');
        
        if(!settings['appearance.theme'].includes('auto')) {
            return false;
        }
        
        let newTheme = e.matches ? 'dark_auto' : 'light_auto';
        magistraal.settings.set('appearance.theme', newTheme);
    })
}

/* ============================ */
/*          Input tags          */
/* ============================ */

$.fn.addTag = function(value, text = undefined) {
    let $wrapper = this.closest('.input-tags-wrapper');
    let $tags    = $wrapper.find('.input-tags-list');

    if(typeof text == 'undefined') {
        text = value;
    }

    let tags = $wrapper.data('tags') || {};
    if(typeof tags[value] != 'undefined') {
        return;
    }

    $(`<li class="input-tags-tag" value="${value}">${text}</li>`).insertBefore($tags.find('.input-tags'));

    tags[value] = text;
    $wrapper.data('tags', tags);

    return this;
}

$.fn.removeTag = function(value) {
    let $wrapper = this.closest('.input-tags-wrapper');
    let $tags    = $wrapper.find('.input-tags-list');
    
    let tags = $wrapper.data('tags') || {};
    if(typeof tags[value] == 'undefined') {
        return;
    }

    $tags.find(`.input-tags-tag[value="${value}"]`).remove();

    delete tags[value];
    $wrapper.data('tags', tags);

    return this;
}

$.fn.setTags = function(tags) {
    let $wrapper = this.closest('.input-tags-wrapper');
    let $tags    = $wrapper.find('.input-tags-list');

    $tags.find('.input-tags-tag').remove();
    $wrapper.data('tags', {});

    $.each(tags, function(value, text) {
        this.addTag(value, text);
    })
}

$(document).on('magistraal.ready', function() {
    $('.input-search').each(function() {
        new magistraal.inputs.searchInput($(this));
    })

    $('.input-tags').each(function() {
        new magistraal.inputs.tagsInput($(this));
    });

    FroalaEditor.ICON_DEFAULT_TEMPLATE = 'font_awesome';
    FroalaEditor.ICON_TEMPLATES = {font_awesome: '<i class="fal fa-[NAME]" aria-hidden="true"></i>'}
    FroalaEditor.DefineIcon('textColor', {NAME: 'paint-brush'});
    FroalaEditor.DefineIcon('fontSize', {NAME: 'text-size'});

    $('.rich-editor').each(function() {
        let $el = $(this);

        $el.attr('data-rich-editor', randomString(8));
        let editor = new FroalaEditor(`[data-rich-editor="${$el.attr('data-rich-editor')}"]`, {
            quickInsertTags: [''],
            charCounterCount: false,
            theme: 'magistraal',
            attribution: false,
            toolbarButtons: {
                moreMisc: {
                    buttons: [
                        'bold',
                        'italic',
                        'underline',
                        '|',
                        'alignLeft',
                        'alignCenter',
                        'alignRight',
                        '|',
                        'formatOLSimple',
                        'formatULSimple'
                        // 'insertLink'
                    ],
                    buttonsVisible: 10,
                    align: 'left'
                }
            },
            placeholderText: magistraal.locale.translate($el.attr('data-translation')),
            fontSize: [8, 9, 10, 11, 12, 14, 16, 18, 20, 22, 24, 28, 36]
        })

        $el.data('editor', editor);
    })
})

$(document).on('input', '[data-magistraal-search-target]', function() {
    let $input         = $(this);
    let $target        = magistraal.element.get($input.attr('data-magistraal-search-target'));
    let $searchResults = $target.find('[data-search]');
    let query          = $(this).val().toLowerCase();

    if(query.length == 0 && (typeof $input.attr('data-magistraal') == 'undefined' || $input.attr('data-magistraal') != 'page-search')) {
        $searchResults.hide();
        return false;
    }

    $searchResults.each(function() {
        let $searchResult = $(this);
        $searchResult.attr('data-search').toLowerCase().includes(query) ? $searchResult.show() : $searchResult.hide();
    });
})


$(document).on('mouseenter', '[data-magistraal-tooltip]', function() {
    magistraal.element.get('tooltip').show();
    
    let translation =  magistraal.locale.translate(`tooltip.hint.${$(this).attr('data-magistraal-tooltip')}`, $(this).attr('data-magistraal-tooltip'));
    
    magistraal.element.get('tooltip').text(translation);
}).on('mousemove', function(e) {
    if(
        typeof $(e.target).attr('data-magistraal-tooltip') == 'undefined' &&
        $(e.target).parents('[data-magistraal-tooltip]').length == 0
    ) {
        magistraal.element.get('tooltip').hide();
        return true;
    }

    magistraal.element.get('tooltip').show();
    magistraal.element.get('tooltip').css({'top': e.pageY, 'left': e.pageX});
})

// $('*').on('scroll', function() {
//     if($(this).find('[data-magistraal-tooltip]:hover').length == 0) {
//         magistraal.element.get('tooltip').hide();
//     }
// })

$(document).on('click', '[data-magistraal="popup-backdrop"]', function(e) {
    if($('[data-magistraal-popup].show').length > 0) {
        magistraal.popup.close($('[data-magistraal-popup].show').last().attr('data-magistraal-popup'));
        return false;
    }
})

$(document).on('click', '[data-popup-action]', function(e) {
    e.preventDefault();

    let $button = $(this);
    let action  = $button.attr('data-popup-action');
    let popup   = $button.parents('[data-magistraal-popup]').first().attr('data-magistraal-popup');
    
    switch(action) {
        case 'confirm':
            magistraal.popup.close(popup);
            break;
        case 'cancel':
            magistraal.popup.close(popup);
            break;
    }
})

$(document).ready(function() {
    $('input[type="date"]').each(function() {
        $(this).value(null);
    });
})

$(window).on('hashchange', function() {
    magistraal.page.load(window.location.hash.substring(1));
})

function trim(str, char = ' ') {
    let start = 0;
    let end   = str.length;

    while(start < end && str[start] === char)
        ++start;

    while(end > start && str[end - 1] === char)
        --end;

    return (start > 0 || end < str.length) ? str.substring(start, end) : str;
}

function setCookie(name, value, expires = null) {
    if(expires === null) {
        expires = 365*24*60*60;
    }
    
    const date = new Date();
    date.setTime(date.getTime() + expires);
    expires = 'expires=' + date.toUTCString();
    document.cookie = name + '=' + value + ';' + expires + ';path=/; SameSite=None; secure';
}

function getCookie(name) {
    name = name + '=';
    let decodedCookie = decodeURIComponent(document.cookie);
    let ca = decodedCookie.split(';');
    for(let i = 0; i <ca.length; i++) {
        let c = ca[i];
        while (c.charAt(0) == ' ') {
            c = c.substring(1);
        }
        if (c.indexOf(name) == 0) {
            return c.substring(name.length, c.length);
        }
    }
    return '';
}

function random(min, max) {
    return Math.floor(Math.random() * (max - min + 1) + min);
}

function randomString(length) {
    let result           = '';
    let characters       = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    let charactersLength = characters.length;
    
    for (let i = 0; i < length; i++) {
        result += characters.charAt(Math.floor(Math.random() * charactersLength));
    }

    return result;
}

function addLeadingZero(n) {
  return (n < 10 ? '0' : '') + n;
}

function capitalizeFirst(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
}

function isNumeric(str) {
    if (typeof str == 'number') return true;
    if (typeof str != 'string') return false;
    return !isNaN(str) &&
           !isNaN(parseFloat(str));
}

function escapeQuotes(str) {
    return str.replace('\'', '\\x27').replace('\"', '\\x22');
}

function addToObject(obj, key, value, index) {
	let temp = {};
	let i = 0;

	for (let prop in obj) {
		if (obj.hasOwnProperty(prop)) {

			if (i === index && key && value) {
				temp[key] = value;
			}

			temp[prop] = obj[prop];

			i++;

		}
	}

	if (!index && key && value) {
		temp[key] = value;
	}

	return temp;
};

// $.fn.sidebarContent = function(sidebarContent = undefined) {
//     if(typeof sidebarContent == 'undefined') {
//         if(typeof this.attr('data-sidebar-content') == 'undefined') {
//             return {'title': '', 'subtitle': '', 'table': {}};
//         } else {
//             return JSON.parse(decodeURIComponent(this.attr('data-sidebar-content')));
//         }
//     } else if(sidebarContent == '') {
//         this.removeAttr('data-sidebar-content');
//     } else {
//         this.attr('data-sidebar-content', encodeURIComponent(JSON.stringify(sidebarContent)));
//     }

//     return true;
// }

// function arrayColumn(array, columnKey, indexKey = undefined) {
//     if(typeof indexKey == 'undefined') {
//         return array.map(function(item, index) {
//             return item[columnKey];
//         })
//     } else {
//         let output = [];

//         $.each(array, function(index, item) {
//             if(typeof item == 'undefined' || typeof item[columnKey] == 'undefined') {
//                 return true;
//             }

//             output[item[columnKey]] = item[indexKey];
//         });

//         return output;
//     }
// }

// function arrayChangeKeyToValueIndex(array, indexKey) {
//     let output = [];

//     $.each(array, function(index, item) {
//         if(typeof item[indexKey] == 'undefined') {
//             return true;
//         }

//         output[item[indexKey]] = item;
//     })

//     return output;
// }