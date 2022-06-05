function debounce(callback, delay = 500) {
    let timeout;

    return (...args) => {
        clearTimeout(timeout);
        timeout = setTimeout(() => {
            callback(...args);
        }, delay)
    }
}

function throttle(callback, delay = 1000) {
    let shouldWait = false;
    let waitingArgs;
    const timeoutFunc = () => {
        if (waitingArgs == null) {
            shouldWait = false;
        } else {
            callback(...waitingArgs);
            waitingArgs = null;
            setTimeout(timeoutFunc, delay);
        }
    }

    return (...args) => {
        if (shouldWait) {
            waitingArgs = args;
            return;
        }

        callback(...args);
        shouldWait = true;

        setTimeout(timeoutFunc, delay);
    }
}

function unique(array) {
    return [...new Set(array)];
}

function isSet(variable) {
    return !(variable === null || typeof variable == 'undefined');
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
    const $form  = this;
    const form   = this.get(0);
    const output = {};

    if(form.nodeName.toLowerCase() != 'form') {
        return false;
    }

    $form.find('[name]').each(function() {
        const $el = $(this);

        // Continue if input is empty
        if($el.val().trim().length == 0 && !$el.hasClass('input-tags')) {
            return true;
        } 

        if($el.get(0).nodeName.toLowerCase() == 'textarea') {
            // Replace newline with br
            output[$el.attr('name')] = $el.value().replace(/\r\n|\r|\n/g, '<br>');
        } else {
            output[$el.attr('name')] = $el.value();
        }
    })

    return output;
}

$.fn.formHasChanges = function() {
    const $form  = this;
    const form   = this.get(0);

    if(form.nodeName.toLowerCase() != 'form') {
        return false;
    } 

    return isSet($form.attr('data-has-changes')) && $form.attr('data-has-changes') == 'true';
}

$(document).on('input', 'input, textarea, [contenteditable]', throttle(function(e) {
    let $form = $(e.target).closest('form');

    if($form.length == 0) {
        return false;
    }

    $form.attr('data-has-changes', true);
}, 1000))

$.fn.formReset = function() {
    const $form  = this;
    const form   = this.get(0);

    if(form.nodeName.toLowerCase() != 'form') {
        return false;
    }

    $form.find('input, textarea, [contenteditable]').each(function() {
        const $el = $(this);
        if($el.hasClass('input-tags')) {
            $el.setTags({});
            $el.val('');
            $el.data('searchInput').results.set({});
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

    $form.removeAttr('data-has-changes');
}

$(document).on('click', function(e) {
    if($(e.target).closest('[data-magistraal="nav-toggler"]').length == 0 && 
       $('nav').length > 0 && 
       magistraalStorage.get('nav_active').value === true
    ) {
        e.preventDefault();
        magistraal.nav.close();
    }
})

/* ============================ */
/*           On/Offline         */
/* ============================ */


function isOnline() {
    return window.navigator.onLine;
}

$(document).on('magistraal.ready', function() {
    $('body').attr('data-online', isOnline());
})

$(window).on('online offline', function() {
    const userIsOnline = isOnline();

    $('body').attr('data-online', userIsOnline);
    console.log('online', userIsOnline);

    magistraal.console.info(`console.info.internet_${userIsOnline ? 'online' : 'offline'}`);
})

/* ============================ */
/*           Settings           */
/* ============================ */

$(document).on('magistraal.change', '.setting-list-item input', function(e) {
    let $input   = $(this);
    let value    = $input.value();
    let $setting = $input.closest('.setting-list-item');
    let setting  = $setting.attr('data-setting');
    let doReload = ($setting.attr('data-reload') == 'true' ? true : false);

    // Add system theme to auto theme to prevent flash when app starts
    if(setting == 'appearance.theme' && value == 'auto') {
        value = (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark_auto' : 'light_auto');
    }

    magistraal.settings.set(setting, value).then(response => {
        if(doReload) {
            window.location.reload();
        }
    });
})

// If system theme changes
if(window.matchMedia) {
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
        const currentTheme = magistraal.settings.get('appearance.theme');  
        if(!isSet(currentTheme) || !currentTheme.includes('auto')) {
            return false;
        }
        
        let newTheme = e.matches ? 'dark_auto' : 'light_auto';
        magistraal.settings.set('appearance.theme', newTheme);
    })
}

/* ============================ */
/*       responsiveCarousel     */
/* ============================ */

class responsiveCarousel {
    constructor(direction) {
        if(direction != 'x' && direction != 'y') {
            return;
        }

        this.direction      = direction;
        this.$carousel      = $(`<div class="responsive-carousel scrollbar-hidden" data-carousel-direction="${direction}"><div class="responsive-carousel-header" style="display: none;"><div class="responsive-carousel-header-items d-flex flex-row"></div></div><div class="responsive-carousel-body scrollbar-hidden"></div></div>`);
        this.$header        = this.$carousel.find('.responsive-carousel-header');
        this.$headerItems   = this.$header.find('.responsive-carousel-header-items');
        this.$body          = this.$carousel.find('.responsive-carousel-body');
        this.lastSlideIndex = this.getSlideIndex();

        this.$body.on('scroll', (e) => { this.scrollEvent(e); })

        return this;
    }

    addSlide($el, title) {
        let $slide = $el.clone(true);
        $slide.wrap('<div class="responsive-carousel-slide scrollbar-hidden"></div>');
        $slide.parent().appendTo(this.$body);

        // this.$headerItems.append('<span class="responsive-carousel-header-item">EE</span>');

        return this;
    }

    showHeader() {
        this.$header.css('display', '');
    }

    hideHeader() {
        this.$header.hide();
    }

    getSlideCount() {
        return this.$body.find('.responsive-carousel-slide').length;
    }

    setSlideIndex(slideIndex) {
        const $slide = this.$body.find(`.responsive-carousel-slide:nth-child(${slideIndex + 1})`);

        if($slide.length == 0) {
            return this;
        }
        
        this.direction == 'x' ? this.$body.scrollLeft($slide.position().left) : this.$body.scrollTop($slide.position().top);

        return this;
    }

    getSlideIndex() {
        const scrollPos    = this.direction == 'x' ? this.$body.scrollLeft() : this.$body.scrollTop();
        const maxScrollPos = this.direction == 'x' ? this.$body.get(0).scrollWidth : this.$body.get(0).scrollHeight;
        const slideCount   = this.getSlideCount();

        return Math.round((scrollPos / maxScrollPos) * slideCount);
    }

    scrollEvent(e) {
        const slideIndex = this.getSlideIndex();
        const $slide     = this.$body.find(`.responsive-carousel-slide:nth-child(${slideIndex + 1})`);

        // Als de gebruiker naar een andere slide is gescrolled
        if(this.lastSlideIndex != slideIndex) {
            this.updateIndicator();

            if(isSet(this.scrollCallback) && typeof this.scrollCallback == 'function') {
                this.scrollCallback(this, $slide);
            }
        }

        this.lastSlideIndex = slideIndex;
    }

    updateIndicator() {
        const $carouselIndicator = magistraal.element.get('responsive-carousel-indicator');
        const slideIndex         = this.getSlideIndex();

        $carouselIndicator.find('.responsive-carousel-indicator-item.active').removeClass('active');
        $carouselIndicator.find(`.responsive-carousel-indicator-item:nth-child(${slideIndex + 1})`).addClass('active');
    }

    reloadIndicator(showIndicator = false) {
        const $carouselIndicator     = magistraal.element.get('responsive-carousel-indicator');
        const $carouselIndicatorItem = magistraal.template.get('responsive-carousel-indicator-item');
        const slideCount             = this.getSlideCount();

        $carouselIndicator.empty();

        for (let i = 0; i < slideCount; i++) {
            $carouselIndicator.append($carouselIndicatorItem.clone(true).toggleClass('active', i == 0));
        }

        if(showIndicator) {
            $carouselIndicator.addClass('show');

            setTimeout(() => {
                $carouselIndicator.removeClass('show');
            }, 1000);
        }
    }

    jQueryObject() {
        return this.$carousel;
    }
}

$(document).on('touchmove', '.responsive-carousel', function() {
    const $carouselIndicator = magistraal.element.get('responsive-carousel-indicator');
    $carouselIndicator.addClass('show');
})

$(document).on('touchend touchcancel', debounce(function() {
    const $carouselIndicator = magistraal.element.get('responsive-carousel-indicator');
    $carouselIndicator.removeClass('show');  
}, 1000))

/* ============================ */
/*         Notifications        */
/* ============================ */

function usingiOS() {
    return [
        'iPad Simulator',
        'iPhone Simulator',
        'iPod Simulator',
        'iPad',
        'iPhone',
        'iPod'
    ].includes(navigator.platform)
    // iPad on iOS 13 detection
    || (navigator.userAgent.includes('Mac') && "ontouchend" in document)
}

function supportsPWA() {
    return 'serviceWorker' in navigator;
}

// Show Pushcut dialog on iOS
$(document).on('magistraal.ready', function() {
    if(!usingiOS()) {
        return;
    }

    if(isSet(getCookie('ignoreDialog_iOSInstallPushcut'))) {
        return;
    }

    new magistraal.inputs.dialog({
        title: magistraal.locale.translate('generic.dialog.ios_notifications_install_pushcut.title'), 
        description: magistraal.locale.translate('generic.dialog.ios_notifications_install_pushcut.content')
    }).open().then(() => {
        window.open('https://apps.apple.com/app/id1450936447', '_blank', 'noopener');
    }).catch(() => {
        setCookie('ignoreDialog_iOSInstallPushcut', true)
    })
})

let deferredInstallPrompt;
window.addEventListener('beforeinstallprompt', (e) => {
    // Prevent the mini-infobar from appearing on mobile
    e.preventDefault();

    // Store the event so it can be triggered later.
    deferredPrompt = e;

    if(isSet(getCookie('ignoreDialog_installPWA'))) {
        return;
    }

    // Show dialog to let the user know that they can install the PWA
    new magistraal.inputs.dialog({
        title: magistraal.locale.translate('generic.dialog.install_pwa.title'), 
        description: magistraal.locale.translate('generic.dialog.install_pwa.content')
    }).open().then(() => {
        // Show the pwa install prompt
        deferredPrompt.prompt();
    }).catch(() => {
        setCookie('ignoreDialog_installPWA', true)
    })
});

// Show dialog if user has not installed our PWA and the browser supports PWA's
$(document).on('magistraal.ready', function() {
    if(!supportsPWA()) {
        return;
    }

    
})

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

    $.each(tags, (value, text) => {
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

$(document).on('input', '[data-magistraal-target]', function() {
    const $input         = $(this);
    const target         = $input.attr('data-magistraal-target');
    const $target        = magistraal.element.get(target);
    const $searchResults = $target.find('[data-search]');
    const query          = $(this).val().toLowerCase();

    if(typeof $input.attr('data-magistraal') == 'undefined' || $input.attr('data-magistraal') != 'page-search') {
        $searchResults.hide();
        return false;
    }

    $searchResults.each(function() {
        const $searchResult = $(this);
        $searchResult.attr('data-search').toLowerCase().includes(query) ? $searchResult.removeAttr('hidden') : $searchResult.attr('hidden', 'hidden');
        
        if(target == 'main') {
            const $parent = $searchResult.parent('.list-item-group');
            $parent.children('[data-search]:not([hidden])').length > 0 ? $parent.show() : $parent.hide();
        }
    });

    if(target == 'main') {
        // Er is geen enkele match gevonden
        if($target.find('[data-search][hidden]').length == $searchResults.length) {
            magistraal.element.get('page-search-no-matches').addClass('show');
        } else {
            magistraal.element.get('page-search-no-matches').removeClass('show');
        }
    }
})

$(document).on('click', '.dropdown .btn-dropdown', function() {
    const $btn     = $(this);
    const $wrapper = $btn.closest('.dropdown');
    const $target  = $wrapper.find('.dropdown-menu');

    if($target.length == 0) {
        return;
    }

    $wrapper.toggleClass('active');
})

$(document).on('click', function(e) {
    if(isSet($(e.target).attr('data-ignore-event')) && $(e.target).attr('data-ignore-event').includes('click')) {
        return;
    }

    if($(e.target).closest('.dropdown.active').length == 0 || $(e.target).hasClass('dropdown-item')) {
        setTimeout(() => {
            $('.dropdown.active').removeClass('active');
        }, 10);
    }
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

$(document).on('click', '[data-magistraal="popup-backdrop"]', function(e) {
    if($('[data-magistraal-popup].show').length > 0) {
        magistraal.popup.close();
    }
})

$(document).on('click', '[data-popup-action]', function(e) {
    let $button = $(this);
    let action  = $button.attr('data-popup-action');
    let popup   = $button.parents('[data-magistraal-popup]').first().attr('data-magistraal-popup');
    
    disablePopstateEvent(50);
    if(action == 'confirm') {
        // Do not clear form if the action is confirm
        magistraal.popup.close(popup, true, false);
    } else {
        // Clear form if the action is cancel
        magistraal.popup.close(popup, true, true);
    }
})

$(document).on('click', '.sidebar-action[data-action]', function() {
    const $action = $(this);
    magistraal.sidebar.actionStarted($action.attr('data-action'));
})

$(document).ready(function() {
    $('input[type="date"]').each(function() {
        $(this).value(null);
    });
})

let popstateEventDisabled = false;
function disablePopstateEvent(duration = 50) {
    popstateEventDisabled = true;

    setTimeout(() => {
        popstateEventDisabled = false;
    }, duration);
}

$(window).on('popstate', function (e) {
    if(popstateEventDisabled) {
        return;
    }

    magistraal.page.back(false);

    if($('.popup.show').length > 0) {
        magistraal.popup.close(undefined, false, true);
        return;
    }

    magistraal.sidebar.close();
});

$(window).on('hashchange', function(e) {
    magistraal.page.load({
        page: magistraal.page.current()
    });
})

// Forms moeten via Ajax gesubmit worden
$(document).on('submit', 'form', function(e) {
    e.preventDefault();
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
    document.cookie = name + '=' + value + ';' + expires + ';path=/magistraal/;';
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
    return null;
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