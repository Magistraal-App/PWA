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

$.fn.value = function() {
    if(this.hasClass('input-tags')) {
        let $wrapper = this.closest('.input-tags-wrapper');
        let tags   = Object.keys($wrapper.data('tags') || {});
        return tags;
    } else if(this.attr('type') == 'date') {
        return Math.round(new Date(this.val()) / 1000);
    } else if(this.attr('type') == 'time') {
        let [hours, minutes] = this.val().split(':');
        hours = parseInt(hours);
        minutes = parseInt(minutes);

        return hours * 3600 + minutes * 60;
    } else {
        return this.val();
    }
}

$(document).on('click', function(e) {
    if($(e.target).closest('[data-magistraal="nav-toggler"]').length == 0 && magistraalStorage.get('nav_active')) {
        e.preventDefault();
        magistraal.nav.close();
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

// $('form[data-handler="ajax"]').on('submit', function(e) {
//     e.preventDefault();

//     let $form = $(this);
//     let action = $form.attr('action');
//     let data   = Object.fromEntries(new FormData($form.get(0)).entries());
    
//     magistraal.api.call(action, data).catch(() => {}).finally(() => {
//         // Magister.popup.close($('form').closest('.show').attr('data-magistraal-popup'));
//     });
// })

// function httpBuildQuery(arr) {
//     let output = [];

//     for (let key in arr) {
//         if (arr.hasOwnProperty(key)) {
//             output.push(key + '=' + encodeURIComponent(arr[key]));
//         }
//     }

//     output = output.join('&');

//     return output;
// }

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

function random(min, max) {
    return Math.floor(Math.random() * (max - min + 1) + min);
}

function addLeadingZero(n) {
  return (n < 10 ? '0' : '') + n;
}

function capitalizeFirst(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
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