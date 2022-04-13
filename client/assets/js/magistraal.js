function _defineProperty(obj, key, value) { if (key in obj) { Object.defineProperty(obj, key, { value: value, enumerable: true, configurable: true, writable: true }); } else { obj[key] = value; } return obj; }

var _magistraalStorage = {};
const magistraalStorage = {
	get: key => {
		return _magistraalStorage[key] || undefined;
	},
	set: (key, value) => {
		_magistraalStorage[key] = value;
	}
};
const magistraalPersistentStorage = {
	get: key => {
		let value = localStorage.getItem(`magistraal.${key}`);

		if (typeof value != 'undefined' && value !== null) {
			if (value.substring(0, 5) == 'JSON:') {
				return JSON.parse(value.substring(5));
			} else {
				return value;
			}
		}

		return undefined;
	},
	set: (key, value) => {
		if (typeof value == 'object') {
			return localStorage.setItem(`magistraal.${key}`, 'JSON:' + JSON.stringify(value));
		} else if (typeof value == 'undefined') {
			return localStorage.removeItem(`magistraal.${key}`);
		} else {
			return localStorage.setItem(`magistraal.${key}`, value);
		}
	},
	remove: key => {
		return magistraalPersistentStorage.set(key, undefined);
	},
	clear: () => {
		let magistraalItems = [];

		for (let i = 0; i < localStorage.length; i++) {
			if (localStorage.key(i).substring(0, 11) == 'magistraal.') {
				magistraalItems.push(localStorage.key(i));
			}
		}

		for (let i = 0; i < magistraalItems.length; i++) {
			localStorage.removeItem(magistraalItems[i]);
		}

		return true;
	}
};
const magistraal = {
	api: {
		call: (api, data = {}, cachable = false, callback = function() {}, pageScope = undefined) => {
			return new Promise((resolve, reject) => {
				/* Pre-load from cache, only if a callback has been supplied */
				if (cachable) {
					let cachedResponseData = magistraalPersistentStorage.get(`api_response.${api}.${JSON.stringify(data)}`);

					if (typeof cachedResponseData != 'undefined') {
						if (pageScope === null) {
							/* Don't make request if pageScope is null and response is already cached */
							if (typeof callback == 'function') {
								callback(cachedResponseData, 'live');
							}

							resolve(cachedResponseData);
							return true;
						} else {
							callback(cachedResponseData, 'cache');
						}
					}
				}

				$.ajax({
					method: 'POST',
					url: `${magistraalStorage.get('api')}${api}/`,
					data: data,
					headers: {
						'Accept': 'application/json',
						'X-Auth-Token': magistraalPersistentStorage.get('token')
					},
					success: function(response, textStatus, request) {
						/* Save in cache if cachable */
						if (cachable && typeof (response === null || response === void 0 ? void 0 : response.data) != 'undefined') {
							magistraalPersistentStorage.set(`api_response.${api}.${JSON.stringify(data)}`, response === null || response === void 0 ? void 0 : response.data);
						}

						if (typeof pageScope != 'undefined' && pageScope !== null && pageScope != magistraal.page.current()) {
							return false;
						}

						response.request = request;
						response.source = 'live';
						resolve(response);

						if (typeof callback == 'function') {
							callback(response === null || response === void 0 ? void 0 : response.data, 'live');
						}
					},
					error: function(response) {
						var _response$responseJSO;

						if ((response === null || response === void 0 ? void 0 : (_response$responseJSO = response.responseJSON) === null || _response$responseJSO === void 0 ? void 0 : _response$responseJSO.info) == 'token_invalid') {
							magistraalPersistentStorage.remove('token');
							if(api == 'logout') {
								magistraal.page.get('login');
								return;
							}
							magistraal.page.load('login');
						}

						magistraal.console.error();

						if (typeof pageScope != 'undefined' && pageScope != magistraal.page.current()) {
							return false;
						}

						reject(response);
					},
					complete: function(response, textStatus, request) {
						let token = response.getResponseHeader('X-Auth-Token');
						console.log(token);

						if (token) {
							magistraalPersistentStorage.set('token', token);
						}
					}
				});
			});
		}
	},

	/* ============================ */

	/*         Absences         */

	/* ============================ */
	absences: {
		paintList: (absences, source) => {
			let pageContent = '';
			$.each(absences, function (month, data) {
				if (data.absences.length == 0) {
					return true;
				}

				let $absencesGroup = magistraal.template.get('absences-group');
				$absencesGroup.find('.absences-group-title').text(capitalizeFirst(magistraal.locale.formatDate(data.unix, 'Fy')));
				$.each(data.absences, function (i, absence) {
					let $absence = magistraal.template.get('absence-list-item');
					$absence.find('.absence-list-item-title').html(absence.appointment.designation + '<span class="bullet"></span>' + magistraal.locale.formatDate(absence.appointment.start.unix, 'Hi') + ' - ' + magistraal.locale.formatDate(absence.appointment.end.unix, 'Hi'));
					$absence.find('.absence-list-item-icon').text(absence.lesson || absence.abbr);
					$absence.find('.absence-list-item-content').text(absence.designation);
					$absence.attr({
						'data-type': absence.type,
						'data-permitted': absence.permitted,
						'data-search': absence.designation
					}); // let icon = (message.read == true ? 'envelope-open' : 'envelope');
					// $message.find('.message-list-item-icon').html(`<i class="fal fa-${icon}"></i>`);

					magistraal.sidebar.addFeed($absence, {
						'title': absence.appointment.designation,
						'subtitle': absence.designation,
						'table': {
							'absence.date': capitalizeFirst(magistraal.locale.formatDate(absence.appointment.start.unix, 'ldFY')),
							'absence.time': magistraal.locale.formatDate(absence.appointment.start.unix, 'Hi') + ' - ' + magistraal.locale.formatDate(absence.appointment.end.unix, 'Hi'),
							'absence.lesson': absence.lesson,
							'absence.permitted': magistraal.locale.formatBoolean(absence.permitted)
						}
					});
					$absence.appendTo($absencesGroup);
				});
				pageContent += $absencesGroup.prop('outerHTML');
			});
			magistraal.page.setContent(pageContent);
		}
	},

	/* ============================ */

	/*         Appointments         */

	/* ============================ */
	appointments: {
		paintList: (appointments, source) => {
			let pageContent = '';
			$.each(appointments, function (day, data) {
				var _data$appointments;

				let $appointmentsGroup = magistraal.template.get('appointments-group');
				$appointmentsGroup.find('.appointments-group-title').text(capitalizeFirst(magistraal.locale.formatDate(data.unix, 'ldF')));

				if ((data === null || data === void 0 ? void 0 : (_data$appointments = data.appointments) === null || _data$appointments === void 0 ? void 0 : _data$appointments.length) == 0) {
					// No appointments on this day
					let $appointment = magistraal.template.get('appointment');
					$appointment.find('.lesson-number').html('<i class="fal fa-check"></i>');
					$appointment.attr({
						'data-type': 'none',
						'data-status': 'none',
						'data-search': '',
						'data-finishable': false
					}).removeAttr('onclick');
					$appointment.find('.lesson-designation').text(magistraal.locale.translate('appointments.none_this_day'));
					$appointment.find('.bullet').remove();
					$appointment.appendTo($appointmentsGroup);
					return true;
				}

				$.each(data.appointments, function (i, appointment) {
					let $appointment = magistraal.template.get('appointment');
					let appointmentStart = new Date(appointment.start.unix * 1000);
					let appointmentEnd = new Date(appointment.end.unix * 1000);
					let attachmentsHTML = ''; // Set attributes

					$appointment.attr({
						'data-finished': appointment.finished,
						'data-finishable': true,
						'data-has-meeting-link': appointment.has_meeting_link,
						'data-id': appointment.id,
						'data-search': `${appointment.subjects.join(', ')} ${appointment.designation} ${appointment.content_text}`.trim(),
						'data-status': appointment.status,
						'data-info-type': appointment.info_type,
						'data-creator': appointment.creator
					}); // Set lesson number / icon

					if (appointment['start']['lesson'] > 0) {
						$appointment.find('.lesson-number').text(appointment['duration']['lessons'] <= 1 ? appointment['start']['lesson'] : `${appointment['start']['lesson']}-${appointment['end']['lesson']}`);
					} else {
						$appointment.find('.lesson-number').html(appointment.status == 'schedule' ? '<i class="fal fa-info"></i>' : '');
					}

					if (appointment['has_meeting_link']) {
						$appointment.find('.lesson-join-ms-teams').attr('href', appointment['meeting_link']);
					} // Set lesson times


					$appointment.find('.lesson-time').text(magistraal.locale.formatDate(appointmentStart, 'Hi') + ' - ' + magistraal.locale.formatDate(appointmentEnd, 'Hi')); // Set designation

					$appointment.find('.lesson-designation').text(appointment['facility'] == '' ? appointment['designation'] : `${appointment['designation']} (${appointment['facility']})`); // Set content

					$appointment.find('.lesson-content').html(appointment['content_text']); // Set type

					let lessonType = magistraal.locale.translate(`appointments.appointment.info_type.${appointment.info_type}`);
					$appointment.find('.lesson-type').text(lessonType); // Format attachments as html

					let sidebarFeed = {
						'title': appointment['designation'],
						'subtitle': `${addLeadingZero(appointmentStart.getHours())}:${addLeadingZero(appointmentStart.getMinutes())} - ${addLeadingZero(appointmentEnd.getHours())}:${addLeadingZero(appointmentEnd.getMinutes())}`,
						'table': {
							'appointment.facility': appointment.facility,
							'appointment.start': capitalizeFirst(magistraal.locale.formatDate(appointment.start.unix, 'ldFYHi')),
							'appointment.end': capitalizeFirst(magistraal.locale.formatDate(appointment.end.unix, 'ldFYHi')),
							'appointment.school_subject': appointment.subjects.join(', '),
						}
					};
					sidebarFeed.table[`appointment.info_type.${appointment.info_type}`] = appointment.content;
					sidebarFeed.table['appointment.teachers'] = appointment.teachers.join(', ');
					magistraal.sidebar.addFeed($appointment, sidebarFeed);
					$appointment.appendTo($appointmentsGroup);
				});
				pageContent += $appointmentsGroup.prop('outerHTML');
			});
			magistraal.page.setContent(pageContent);
		},

		view: id => {
			magistraal.console.loading();
			magistraal.api.call('appointments/info', {
				'id': id
			}, true, magistraal.appointments.viewCallback);
		},

		viewCallback: (appointment, source) => {
			let updatedSidebarFeed = {
				'table': {
					'appointment.attachments': '<a>eeeeee</a>'
				}
			};
			
			magistraal.sidebar.updateFeed(updatedSidebarFeed, 'appointment.teachers');

			if (source == 'live') {
				magistraal.console.success();
			}
		},

		finish: (id, finished) => {
			if ($(`.appointment[data-id="${id}"]`).attr('data-finishable') != 'true') {
				return false;
			}

			$(`.appointment[data-id="${id}"]`).attr('data-finished', finished);
			magistraal.console.loading('console.loading.finish_appointment');
			magistraal.api.call('appointments/finish', {
				'id': id,
				'finished': finished
			}).then(() => {
				magistraal.console.success('console.success.finish_appointment');
			}).catch(() => {
				$(`.appointment[data-id="${id}"]`).attr('data-finished', !finished);
			});
		},
		create: options => {}
	},

	/* ============================ */

	/*            Grades            */

	/* ============================ */
	grades: {
		paintList: (grades, source) => {
			let pageContent = '';
			
			$.each(grades, function (i, grade) {
				let $grade    = magistraal.template.get('grade-list-item');
				let enteredAt = magistraal.locale.formatDate(grade['entered_at'], 'dFYHi');

				$grade.attr({
					'data-counts': grade['counts'],
					'data-exemption': grade['exemption'],
					'data-passed': grade['passed'],
					'data-search': `${grade['value_str']} ${grade['subject']['description']} ${grade['description']} ${enteredAt}`,
					'data-value': grade['value'],
					'data-weight': grade['weight']
				});

				$grade.find('.list-item-icon').text(grade['value_str']);
				$grade.find('.grade-subject').text(grade['subject']['description']);
				$grade.find('.grade-description').text(grade['description']);
				$grade.find('.grade-weight').text(`${grade['weight']}x`);
				$grade.find('.grade-entered-at').text(enteredAt);

				let sidebarFeed = {
					'title': grade['subject']['description'],
					'subtitle': capitalizeFirst(grade['description']),
					'table': {
						'grade.value': grade['value_str'],
						'grade.weight': `${grade['weight']}x`,
						'grade.entered_at': capitalizeFirst(enteredAt),
						'grade.counts': magistraal.locale.formatBoolean(grade['counts']),
						'grade.exemption': magistraal.locale.formatBoolean(grade['exemption'])
					}
				};

				magistraal.sidebar.addFeed($grade, sidebarFeed);

				pageContent += $grade.prop('outerHTML');
			});

			magistraal.page.setContent(pageContent);
		}
	},

	/* ============================ */

	/*           Messages           */

	/* ============================ */
	messages: {
		paintList: (messages, source) => {
			/* Pre-load newest three messages while painting */
			magistraal.api.call('messages/info', {'id': String(messages[0].id)}, true, () => {}, null);
			magistraal.api.call('messages/info', {'id': String(messages[1].id)}, true, () => {}, null);
			magistraal.api.call('messages/info', {'id': String(messages[2].id)}, true, () => {}, null);

			let pageContent = '';
			$.each(messages, function (i, message) {
				let $message = magistraal.template.get('message-list-item');
				message.subject = message.subject || magistraal.locale.translate('messages.subject.no_subject');
				$message.find('.message-list-item-title').text(message.subject);
				$message.find('.message-list-item-side-title').text();
				$message.find('.message-list-item-content').text(message.sender.name);
				$message.attr({
					'data-id': message.id,
					'data-priority': message.priority,
					'data-read': message.read,
					'data-search': message.subject + message.sender.name
				});

				let icon = message.read == true ? 'envelope-open' : 'envelope';
				$message.find('.message-list-item-icon').html(`<i class="fal fa-${icon}"></i>`);

				magistraal.sidebar.addFeed($message, {
					'title': message.subject,
					'subtitle': message.sender.name,
					'table': {
						'message.sender': message.sender.name,
						'message.sent_at': capitalizeFirst(magistraal.locale.formatDate(message.sent_at, 'ldFYHi'))
					}
				});

				pageContent += $message.prop('outerHTML');
			});
			magistraal.page.setContent(pageContent);
		},

		view: (id, read = true) => {
			magistraal.console.loading();
			magistraal.api.call('messages/info', {
				'id': id
			}, true, magistraal.messages.viewCallback, null);

			if (!read) {
				magistraal.api.call('messages/read', {
					'id': id,
					'read': !read
				}).catch(() => {});
			}
		},

		viewCallback: (message, source) => {
			let sidebarFeed = {
				'title': message.subject || magistraal.locale.translate('messages.subject.no_subject'),
				'table': {
					'message.sender': message.sender.name,
					'message.sent_at': magistraal.locale.formatDate(message.sent_at, 'ldFYHi'),
					'message.to': message.recipients.to.names.join(', '),
					'message.cc': message.recipients.cc.names.join(', '),
					'message.bcc': message.recipients.bcc.names.join(', '),
					'message.content': message.content
				}
			};
			magistraal.sidebar.setFeed(sidebarFeed);

			if (source == 'live') {
				magistraal.console.success();
			}
		},

		send: $form => {
			let data = {
				'to': $form.find('[name="to"]').value(),
				'cc': $form.find('[name="cc"]').value(),
				'bcc': $form.find('[name="bcc"]').value(),
				'subject': $form.find('[name="subject"]').value(),
				'content': $form.find('[name="content"]').value().replace(/\r\n|\r|\n/g, '<br/>')
			};
			magistraal.element.get('send-message').attr('disabled', 'disabled');
			magistraal.popup.close('messages-write-message');
			magistraal.console.loading('console.loading.send_message');
			setTimeout(() => {
				magistraal.api.call('messages/send', data, false).then(response => {
					magistraal.console.success('console.success.send_message');
				}).catch(err => {
					console.error(err);
					magistraal.console.error('console.error.send_message');
					magistraal.popup.open('messages-write-message');
				}).finally(() => {
					// Reset form
					$form.find('[name="to"], [name="cc"], [name="bcc"]').setTags({});
					$form.find('[name="subject"], [name="content"]').val('');
					magistraal.element.get('send-message').removeAttr('disabled');
					magistraal.element.get('cancel-message').removeAttr('disabled');
				});
			}, 500);
		}
	},

	/* ============================ */

	/*           SETTINGS           */

	/* ============================ */
	settings: {
		paintList: settings => {
			let pageContent = '';
			$.each(settings.items, function (itemNamespace, item) {
				if (typeof item.items != 'undefined') {
					// Item is a category
					let $settingCategory = magistraal.template.get('setting-category');
					$settingCategory.find('.setting-category-title').text(magistraal.locale.translate(`settings.category.${settings.category}.${itemNamespace}.title`));
					$settingCategory.find('.setting-category-icon').html(`<i class="${(item === null || item === void 0 ? void 0 : item.icon) || 'cog'}"></i>`); // Create content description which consists of the children's names of this items

					let content = '';
					$.each(item.items, function (childItemNamespace, childItem) {
						if (typeof childItem.items == 'undefined') {
							// Child item is a setting
							content += magistraal.locale.translate(`settings.setting.${itemNamespace}.${childItemNamespace}.title`) + ', ';
						} else {
							content += magistraal.locale.translate(`settings.category.${itemNamespace}.${childItemNamespace}.title`) + ', ';
						}
					});
					content = content.slice(0, -2); // Remove last ', ' from string

					$settingCategory.find('.setting-category-content').text(content);
					$settingCategory.attr('onclick', `magistraal.page.load('settings/list', {'category': '${itemNamespace}'});`);
					pageContent += $settingCategory.prop('outerHTML');
				} else {// Item is a setting
				}
			});
			magistraal.page.setContent(pageContent);
		}
	},

	console: {
		info: message => {
			return magistraal.console.send(message, 'info', 2500);
		},

		error: (message = 'console.error.generic') => {
			return magistraal.console.send(message, 'error', -1);
		},

		success: (message = 'console.success.generic') => {
			return magistraal.console.send(message, 'success', 1500);
		},

		loading: (message = 'console.loading.generic') => {
			return magistraal.console.send(message, 'loading', -1);
		},

		send: (message, type = 'success', duration = 1500) => {
			message = magistraal.locale.translate(message, message);
			let messageId = Date.now();
			console.log(`${type}: ${message}`);
			let styles = {
				'info': {
					'icon': 'info-circle',
					'color': 'var(--secondary)'
				},
				'loading': {
					'icon': 'circle-notch fa-spin',
					'color': 'var(--secondary)'
				},
				'debug': {
					'icon': 'info-circle',
					'color': 'var(--secondary)'
				},
				'error': {
					'icon': 'exclamation-circle',
					'color': 'var(--danger)'
				},
				'success': {
					'icon': 'check-circle',
					'color': 'var(--success)'
				}
			};

			let style = styles[type] || styles['info'];

			$('.console-message').remove();

			magistraal.element.get('console').prepend(`
								<div class="console-message" data-magistraal="console-message-${messageId}">
										<span class="console-message-content">${message}</span>
										<span class="console-message-icon pl-1"><i class="fal fa-${style.icon}" style="color: ${style.color};"></i></span>
								</div>
						`); // Delete message after duration passed

			if (duration >= 0) {
				setTimeout(() => {
					magistraal.element.get(`console-message-${messageId}`).remove();
				}, duration);
			}

			return messageId;
		}
	},

	load: () => {
		magistraalStorage.set('api', '/magistraal/api/');

		return new Promise((resolve, reject) => {
			// Load absences, appointments, grades, messages, etc. for offline use
			// magistraal.api.call('absences/list', {}, true);
			// magistraal.api.call('appointments/list', {}, true);
			// magistraal.api.call('grades/list', {}, true);
			// magistraal.api.call('messages/list', {}, true);
			// magistraal.api.call('settings/list', {}, true);

			magistraal.locale.load('nl_NL').then(() => {
				$(document).trigger('magistraal.ready');
				magistraal.console.success();
				resolve();
			}).catch(() => {});
		});
	},

	locale: {
		load: locale => {
			return new Promise((resolve, reject) => {
				magistraal.api.call('locale', {
					'locale': locale
				}, true, magistraal.locale.loadCallback).finally(() => {
					resolve();
				}).catch(() => {});
			});
		},

		loadCallback: (localeData, source) => {
			magistraalStorage.set('translations', localeData);
			$('[data-translation]').each(function () {
				if (this.tagName.toLowerCase() === 'input') {
					// Set placeholder on input elements
					$(this).attr('placeholder', magistraal.locale.translate($(this).attr('data-translation')));
				} else {
					// Set text on other types of elements
					$(this).text(magistraal.locale.translate($(this).attr('data-translation')));
				}
			});
		},

		translate: (key, fallback = undefined) => {
			fallback = fallback || key;
			let translations = magistraalStorage.get('translations');

			if (typeof translations == 'undefined' || typeof translations[key] == 'undefined') {
				return fallback;
			}

			return translations[key];
		},

		formatBoolean: boolean => {
			if (boolean == '1' || boolean == 'yes' || boolean == 'true') {
				return magistraal.locale.translate('generic.bool.true', 'true');
			} else if (boolean == '0' || boolean == 'no' || boolean == 'false') {
				return magistraal.locale.translate('generic.bool.false', 'false');
			} else {
				return '';
			}
		},

		formatDate: (date, format) => {
			if (typeof date != 'object') {
				// Convert unix to date object
				date = new Date(date * 1000);
			}

			let output = '';

			switch (format) {
				case 'Hi':
					output += addLeadingZero(date.getHours()) + ':';
					output += addLeadingZero(date.getMinutes());
					break;

				case 'dF':
					output += date.getDate() + ' ';
					output += magistraal.locale.translate(`generic.month.${date.getMonth()}`);
					break;

				case 'ldF':
					output += magistraal.locale.translate(`generic.day.${date.getDay()}`) + ' ';
					output += date.getDate() + ' ';
					output += magistraal.locale.translate(`generic.month.${date.getMonth()}`);
					break;

				case 'Fy':
					output += magistraal.locale.translate(`generic.month.${date.getMonth()}`) + ' ';
					output += date.getFullYear();
					break;

				case 'dFY':
					output += date.getDate() + ' ';
					output += magistraal.locale.translate(`generic.month.${date.getMonth()}`) + ' ';
					output += date.getFullYear();
					break;

				case 'dmY':
					output += addLeadingZero(date.getDate()) + '-';
					output += addLeadingZero(date.getMonth() + 1) + '-';
					output += date.getFullYear();
					break;

				case 'ldFY':
					output += magistraal.locale.translate(`generic.day.${date.getDay()}`) + ' ';
					output += date.getDate() + ' ';
					output += magistraal.locale.translate(`generic.month.${date.getMonth()}`) + ' ';
					output += date.getFullYear();
					break;

				case 'dmYHi':
					output += addLeadingZero(date.getDate()) + '-';
					output += addLeadingZero(date.getMonth() + 1) + '-';
					output += date.getFullYear() + ' ';
					output += magistraal.locale.translate('generic.date_time_seperator') + ' ';
					output += addLeadingZero(date.getHours()) + ':';
					output += addLeadingZero(date.getMinutes());
					break;

				case 'dFYHi':
					output += date.getDate() + ' ';
					output += magistraal.locale.translate(`generic.month.${date.getMonth()}`) + ' ';
					output += date.getFullYear() + ' ';
					output += magistraal.locale.translate('generic.date_time_seperator') + ' ';
					output += addLeadingZero(date.getHours()) + ':';
					output += addLeadingZero(date.getMinutes());
					break;

				case 'ldFYHi':
				default:
					output += magistraal.locale.translate(`generic.day.${date.getDay()}`) + ' ';
					output += date.getDate() + ' ';
					output += magistraal.locale.translate(`generic.month.${date.getMonth()}`) + ' ';
					output += date.getFullYear() + ' ';
					output += magistraal.locale.translate('generic.date_time_seperator') + ' ';
					output += addLeadingZero(date.getHours()) + ':';
					output += addLeadingZero(date.getMinutes());
					break;
			}

			return output;
		}
	},

	nav: {
		open: () => {
			$('body').attr('data-nav-active', true);
			magistraalStorage.set('nav_active', true);
		},

		close: () => {
			$('body').attr('data-nav-active', false);
			magistraalStorage.set('nav_active', false);
		},

		toggle: () => {
			if (magistraalStorage.get('nav_active') == 'true') {
				magistraal.nav.close();
			} else {
				magistraal.nav.open();
			}
		}
	},

	page: {
		load: (page, data = {}, cachable = true, cacheOnly = false) => {
			page = trim(page.replace(/[^a-zA-Z\/]/g, ''), '/');

			if (page == 'login' || page == 'main') {
				window.location.href = `../${page}/`;
				return true;
			}

			history.pushState(null, null, window.location.pathname + '#' + page + '/' + random(1000, 9999));
			magistraal.sidebar.clearFeed();
			magistraal.sidebar.close();
			magistraal.element.get('main').scrollTop(0);
			$('.nav-item').removeClass('active');
			magistraal.element.get(`nav-item-${page}`).addClass('active');
			magistraal.page.get(page, data, cachable);
		},

		get: (page, data = {}, cachable = true, cacheOnly = false) => {
			magistraal.element.get('page-search').val('');
			page = trim(page, '/');
			let painters = {
				'absences/list': magistraal.absences.paintList,
				'appointments/list': magistraal.appointments.paintList,
				'grades/list': magistraal.grades.paintList,
				'messages/list': magistraal.messages.paintList,
				'logout': magistraal.logout.logout,
				'settings/list': magistraal.settings.paintList
			};

			let painter = () => {};

			if (typeof painters[page] != 'undefined' && !cacheOnly) {
				painter = painters[page];
			}

			let $pageButtonsTemplate = magistraal.template.get(`page-buttons-${page}`);
			let $pageButtonsContainer = magistraal.element.get('page-buttons-container');

			if ($pageButtonsTemplate.length > 0) {
				$pageButtonsContainer.html($pageButtonsTemplate.html());
			} else {
				$pageButtonsContainer.html('');
			}

			$('body').attr('data-page-buttons', $pageButtonsContainer.find('.btn').length); // Change page title

			magistraal.element.get('page-title').text(magistraal.locale.translate(`page.${page}.title`));
			return new Promise((resolve, reject) => {
				try {
					magistraal.page.request(page, data, painter, cachable).then(response => {
						resolve(response);
					}).catch(() => {});
				} catch {
					magistraal.console.error();
				}
			});
		},
		
		request: (page, data = {}, painter = null, cachable = true) => {
			return new Promise((resolve, reject) => {
				magistraal.console.loading();
				magistraal.api.call(page, data, cachable, painter, page).then(response => {
					magistraal.console.success();
					resolve(response);
				}).catch(response => {
					reject(response);
				});
			});
		},
		current: () => {
			return trim(window.location.hash.substring(1).replace(/[^a-zA-Z\/]/g, ''), '/');
		},
		
		setContent: (html = '') => {
			$('main').html(html);
		}
	},
	login: {
		login: $form => {
			let action = $form.attr('action');
			magistraal.console.loading('console.loading.login');
			magistraal.api.call(action, $form.serialize()).then(response => {
				let token = response.request.getResponseHeader('X-Auth-Token');

				if (!token) {
					magistraal.console.error(`console.error.${response === null || response === void 0 ? void 0 : response.info}`);
					return false;
				}

				magistraalStorage.set('token', token);
				magistraal.console.success('console.success.login');
				window.location.href = '../main/';
			}).catch(response => {
				var _response$responseJSO2;

				magistraal.console.error(`console.error.${response === null || response === void 0 ? void 0 : (_response$responseJSO2 = response.responseJSON) === null || _response$responseJSO2 === void 0 ? void 0 : _response$responseJSO2.info}`);
			});
		}
	},
	inputs: {
		tagsInput: class {
			constructor($input) {
				this.$input = $input;
				this.setup();
			}

			setup() {
				if (this.$input.closest('.input-wrapper').length == 0) {
					this.$input.wrap('<div class="input-wrapper"></div>');
				}

				this.$wrapper = this.$input.closest('.input-wrapper');
				this.$wrapper.addClass('input-tags-wrapper');
				this.$tags = $('<ul class="input-tags-list"></ul>');
				this.$tags.appendTo(this.$wrapper); // Create ghost input

				if (this.$wrapper.hasClass('input-search-wrapper')) {
					this.$inputGhost = $('<input type="text" class="form-control input-search input-ghost">');
					this.$inputGhost.appendTo(this.$wrapper); // Move search input to tags list

					this.$tags.append(this.$input.removeClass('input-search').detach());
				}

				this.$input.on('click', e => {
					this.eventFocus(e);
				});
				this.$wrapper.on('focusout', e => {
					this.eventFocusOut(e);
				});
				this.$input.on('magistraal.change', e => {
					this.eventChange(e);
				});
				this.$input.on('keyup', e => {
					this.eventKeyup(e);
				});
			}

			eventFocus(e) {
				this.$inputGhost.addClass('focus');
			}

			eventFocusOut(e) {
				this.$inputGhost.removeClass('focus');
			}

			eventChange(e) {
				if (typeof e.addTag != undefined) {
					var _e$addTag, _e$addTag2;

					this.$input.addTag((_e$addTag = e.addTag) === null || _e$addTag === void 0 ? void 0 : _e$addTag.value, (_e$addTag2 = e.addTag) === null || _e$addTag2 === void 0 ? void 0 : _e$addTag2.text);
				}
			}

			eventKeyup(e) {
				if (e.which == 8) {
					// Backspace, remove last tag
					let lastTagValue = this.$tags.find('.input-tags-tag:last-of-type').attr('value');
					this.$input.removeTag(lastTagValue);
				}
			}

		},
		searchInput: class searchInput {
			constructor($input) {
				_defineProperty(this, "results", {
					set: results => {
						let html = '';
						$.each(results, function (i, result) {
							html += `<li class="input-search-result input-search-result-rich" value="${result.value}"><i class="input-search-result-icon ${result === null || result === void 0 ? void 0 : result.icon}"></i><span class="input-search-result-title">${result.title}</span><span class="input-search-result-description">${result.description}</span></li>`;
						});
						this.$results.html(html);
					}
				});

				this.$input = $input;
				this.setup();
			}

			setup() {
				if (this.$input.closest('.input-wrapper').length == 0) {
					this.$input.wrap('<div class="input-wrapper"></div>');
				}

				this.$wrapper = this.$input.closest('.input-wrapper');
				this.$wrapper.addClass('input-search-wrapper');
				this.$results = $('<ul class="input-search-results"></ul>');
				this.$results.appendTo(this.$wrapper);
				this.$input.attr('placeholder', magistraal.locale.translate('generic.action.search'));
				this.$input.on('click', e => {
					this.eventFocus(e);
				});
				this.$input.on('focusout', e => {
					this.eventFocusOut(e);
				});
				this.$input.on('input', debounce(e => {
					this.eventInputDebounced(e);
				}, 250));
				this.$input.on('magistraal.change', e => {
					this.eventChange(e);
				});
				this.$results.on('click', '.input-search-result', e => {
					this.eventResultClick(e);
				});
			}

			eventFocus(e) {
				this.$wrapper.addClass('active');
			}

			eventFocusOut(e) {
				setTimeout(() => {
					this.$wrapper.removeClass('active');
				}, 50);
			}

			eventInputDebounced(e) {
				if (typeof this.$input.attr('data-magistraal-search-api') != 'undefined') {
					// Fetch data from api
					let api = this.$input.attr('data-magistraal-search-api');
					let query = this.$input.val() || this.$input.text();
					magistraal.api.call(`${api}/search`, {
						query: query
					}, false).then(response => {
						let results = magistraal.inputs.search.remap_api_response(api, response === null || response === void 0 ? void 0 : response.data);
						this.results.set(results);
					}).catch(err => {
						console.error(err);
					});
				}
			}

			eventResultClick(e) {
				var _$result$find;

				let $result = $(e.target).closest('.input-search-result');
				let value = $result.attr('value');
				let text = ((_$result$find = $result.find('.input-search-result-title')) === null || _$result$find === void 0 ? void 0 : _$result$find.text()) || $result.text();
				this.$input.val('');

				if (this.$wrapper.hasClass('input-tags-wrapper')) {
					let $inputTags = this.$wrapper.find('.input-tags');
					$inputTags.trigger({
						type: 'magistraal.change',
						addTag: {
							value: value,
							text: text
						}
					});
					this.$input.focus();
					setTimeout(() => {
						var _this$$wrapper$find;

						this.$wrapper.addClass('active');
						(_this$$wrapper$find = this.$wrapper.find('.input-ghost')) === null || _this$$wrapper$find === void 0 ? void 0 : _this$$wrapper$find.addClass('focus');
					}, 50);
				}
			}

			eventChange(e) {}

		},
		search: {
			remap_api_response: (api, data) => {
				let result = [];

				switch (api) {
					case 'people':
						$.each(data, function (i, person) {
							result.push({
								'icon': person.type == 'student' ? 'fal fa-book-open' : 'fal fa-briefcase',
								'title': person.infix == '' ? `${person.first_name} ${person.last_name}` : `${person.first_name} ${person.infix} ${person.last_name}`,
								'description': person.course || person.abbr || magistraal.locale.translate(`people.type.${person.type}`),
								'value': person.id
							});
						});
						break;
				}

				return result;
			}
		}
	},
	logout: {
		logout: () => {
			console.log('UITLOGGEN!!');
			magistraalPersistentStorage.clear();
			magistraal.page.load('login', {}, false);
		}
	},
	sidebar: {
		addFeed: ($elem, feed) => {
			$elem.attr('data-sidebar-feed', encodeURI(JSON.stringify(feed)));
			return true;
		},

		clearFeed: () => {
			return magistraal.sidebar.selectFeed(null);
		},

		getFeed: $elem => {
			return JSON.parse(decodeURI($elem.attr('data-sidebar-feed') || '{}'));
		},

		selectFeed: ($elem, openSidebar = true) => {
			if ($elem == null) {
				return magistraal.sidebar.setFeed(undefined, false);
			}

			$(`[class="${$elem.attr('class')}"]`).removeAttr('data-selected');
			$elem.attr('data-selected', true);
			magistraal.sidebar.setFeed(magistraal.sidebar.getFeed($elem), openSidebar);
		},

		setFeed: (feed = {
			'title': '',
			'subtitle': '',
			'table': []
		}, openSidebar = true) => {
			let $sidebarTable = magistraal.element.get('sidebar-table');
			magistraal.element.get('sidebar-title').text(feed.title);
			magistraal.element.get('sidebar-subtitle').text(feed.subtitle);
			$sidebarTable.empty();
			$.each(feed.table, function (tableKey, tableValue) {
				if (tableKey == '' || tableValue == '') {
					return true;
				}

				let $tableKey = magistraal.template.get('sidebar-table-cell').addClass('sidebar-table-key h5 font-heading');
				$tableKey.attr('data-key', tableKey).text(magistraal.locale.translate(`sidebar.table.info.${tableKey}`));
				$tableKey.appendTo($sidebarTable);
				let $tableValue = magistraal.template.get('sidebar-table-cell').addClass('sidebar-table-value');
				$tableValue.html(tableValue);
				$tableValue.appendTo($sidebarTable);
			});

			if (openSidebar) {
				setTimeout(() => {
					magistraal.sidebar.open();
				}, 50);
			}
		},

		updateFeed: (updateWithFeed, insertBeforeKey = undefined) => {
			let newFeed     = {title: '', subtitle: '', table: {}};
			let currentFeed = magistraal.sidebar.getSelectedFeed();
			
			newFeed.title = updateWithFeed?.title || currentFeed.title; 
			newFeed.subtitle = updateWithFeed?.subtitle || currentFeed.subtitle; 

			$.each(currentFeed.table, function(currentTableKey, currentTableValue) {
			    if(currentTableKey == insertBeforeKey) {
					$.each(updateWithFeed.table, function(updateTableKey, updateTableValue) {
						newFeed.table[updateTableKey] = updateTableValue;
					})
				}

				newFeed.table[currentTableKey] = currentTableValue;
			})

			magistraal.sidebar.setFeed(newFeed);
		},

		getSelectedFeed() {
			let selectedFeed = {
				'title': magistraal.element.get('sidebar-title').text(),
				'subtitle': magistraal.element.get('sidebar-subtitle').text(),
				'table': {}
			};

			$('[data-magistraal="sidebar-table"]').find('.sidebar-table-key').each(function () {
				let tableKey = $(this).attr('data-key');
				let tableValue = $(this).next('.sidebar-table-value').first().text();
				selectedFeed.table[tableKey] = tableValue;
			});

			return selectedFeed;
		},

		open: () => {
			$('body').attr('data-sidebar-active', true);
			magistraalStorage.set('sidebar_active', true);
		},
		close: () => {
			$('body').attr('data-sidebar-active', false);
			magistraalStorage.set('sidebar_active', false);
		},
		toggle: () => {
			if (magistraalStorage.get('sidebar_active') == 'true') {
				magistraal.sidebar.close();
			} else {
				magistraal.sidebar.open();
			}
		}
	},
	popup: {
		get: selector => {
			return $(`[data-magistraal-popup="${selector}"]`).first();
		},
		open: selector => {
			let $popup = magistraal.popup.get(selector);

			if ($popup.length === 0) {
				return false;
			}

			magistraal.element.get('popup-backdrop').addClass('show');
			$popup.addClass('show');
		},
		close: selector => {
			let $popup = magistraal.popup.get(selector);

			if ($popup.length === 0) {
				return false;
			}

			magistraal.element.get('popup-backdrop').removeClass('show');
			$popup.removeClass('show');
		}
	},
	template: {
		get: selector => {
			return $(`[data-magistraal-template="${selector}"]`).first().clone().removeAttr('data-magistraal-template');
		}
	},
	element: {
		get: selector => {
			return $(`[data-magistraal="${selector}"]`);
		}
	}
};