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

		if(typeof value != 'undefined' && value !== null) {
			if(value.substring(0, 5) == 'JSON:') {
				return JSON.parse(value.substring(5));
			} else {
				return value;
			}
		}

		return null;
	},

	set: (key, value) => {
		if(typeof value == 'object') {
			return localStorage.setItem(`magistraal.${key}`, 'JSON:' + JSON.stringify(value));
		} else if(typeof value == 'undefined') {
			return localStorage.removeItem(`magistraal.${key}`);
		} else {
			return localStorage.setItem(`magistraal.${key}`, value);
		}
	},

	remove: key => {
		return magistraalPersistentStorage.set(key, undefined);
	},

	clear: (soft = false) => {
		let magistraalItems = [];

		console.log((soft ? 'Soft-clearing' : 'Hard-clearing') + ' cache!');

		for (let i = 0; i < localStorage.length; i++) {
			let key = localStorage.key(i);
			if(key.substring(0, 10) != 'magistraal') {
				continue;
			}

			if(key == 'magistraal.version' || key == 'magistraal.api_response.locale') {
				continue;
			}

			magistraalItems.push(localStorage.key(i));
		}

		for (let i = 0; i < magistraalItems.length; i++) {
			localStorage.removeItem(magistraalItems[i]);
		}

		if(!soft) {
			magistraal.token.delete();
		}

		return true;
	}
};

const magistraal = {
	api: {
		call: (parameters = {}) => {
			if(typeof parameters.callback  == 'undefined') { parameters.callback = function() {}; }
			if(typeof parameters.source    == 'undefined') { parameters.source = 'both'; }
			if(typeof parameters.scope     == 'undefined') { parameters.scope = undefined; }
			if(typeof parameters.url       == 'undefined') { return false; }
			if(typeof parameters.data      == 'undefined') { parameters.data = {}; }
			if(typeof parameters.cachable  == 'undefined') { parameters.cachable = (parameters.source != 'server_only'); }
			if(typeof parameters.xhrFields == 'undefined') { parameters.xhrFields = {}; }

			return new Promise((resolve, reject) => {
				// Response voorladen uit cache, alleen als er een callback is opgegeven
				if(parameters.cachable) {
					let cachedResponseData = magistraalPersistentStorage.get(`api_response.${parameters.url}.${JSON.stringify(parameters.data)}`);
					if(typeof cachedResponseData != 'undefined') {
						// Er is een response opgeslagen, voorladen
						if(typeof parameters.callback == 'function') {
							parameters.callback(cachedResponseData, 'server');
						}

						// Stuur geen request naar de server als source == prefer_cache
						if(parameters.source == 'prefer_cache') {
							resolve(cachedResponseData);
							return true;
						}
					}
				}

				parameters.xhrFields.withCredentials = true;

				// Stuur een request naar de server
				$.ajax({
					method: 'POST',
					url: `${magistraalStorage.get('api')}${parameters.url}/`,
					data: parameters.data,
					headers: {'Accept': '*/*'},
					xhrFields: parameters.xhrFields,
					success: function(response, textStatus, request) {
						if(typeof parameters.scope != 'undefined' && parameters.scope != magistraal.page.current()) {
							return false;
						}

						// Als de request gelukt is
						if(response?.success === true) {
							resolve(response?.data || response);

							// Sla op in cache
							if(parameters.cachable && typeof response.data != 'undefined') {
								magistraalPersistentStorage.set(`api_response.${parameters.url}.${JSON.stringify(parameters.data)}`, response === null || response === void 0 ? void 0 : response.data);
							}
						} else {
							reject(response?.data || response);
						}

						if(typeof parameters.callback == 'function') {
							parameters.callback(response?.data || response, 'server', request);
						}
					},

					error: function(response) {
						if(typeof response?.responseJSON?.info != 'undefined' && response?.responseJSON?.info == 'token_invalid') {
							magistraal.token.delete();
							magistraal.page.load('login');
							return;
						}

						if(typeof parameters.scope != 'undefined' && parameters.scope != magistraal.page.current()) {
							return false;
						}

						magistraal.console.error();
						reject(response);
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
			let $html = $('<div></div>');

			$.each(absences, function (month, data) {
				if(data.absences.length == 0) {
					return true;
				}

				let $absencesGroup = magistraal.template.get('absences-group');
				$absencesGroup.find('.absences-group-title').text(capitalizeFirst(magistraal.locale.formatDate(data.time, 'Fy')));
				
				$.each(data.absences, function (i, absence) {
					let $absence = magistraal.template.get('absence-list-item');
					$absence.find('.absence-list-item-title').html(absence.appointment.designation + '<span class="bullet"></span>' + magistraal.locale.formatDate(absence.appointment.start.time, 'Hi') + ' - ' + magistraal.locale.formatDate(absence.appointment.end.time, 'Hi'));
					$absence.find('.absence-list-item-icon').text(absence.lesson || absence.abbr);
					$absence.find('.absence-list-item-content').text(absence.designation);
					$absence.attr({
						'data-interesting': true,
						'data-permitted': absence.permitted,
						'data-search': absence.designation,
						'data-type': absence.type
					}); // let icon = (message.read == true ? 'envelope-open' : 'envelope');
					// $message.find('.message-list-item-icon').html(`<i class="fal fa-${icon}"></i>`);

					magistraal.sidebar.addFeed($absence, {
						'title': absence.appointment.designation,
						'subtitle': absence.designation,
						'table': {
							'absence.date': capitalizeFirst(magistraal.locale.formatDate(absence.appointment.start.time, 'ldFY')),
							'absence.time': magistraal.locale.formatDate(absence.appointment.start.time, 'Hi') + ' - ' + magistraal.locale.formatDate(absence.appointment.end.time, 'Hi'),
							'absence.lesson': absence.lesson,
							'absence.permitted': magistraal.locale.formatBoolean(absence.permitted)
						}
					});

					$absence.appendTo($absencesGroup);
				});

				$absencesGroup.appendTo($html);
			});
			
			magistraal.page.setContent($html);
		}
	},

	/* ============================ */
	/*         Appointments         */
	/* ============================ */
	appointments: {
		paintList: (appointments) => {
			let $html = $('<div></div>');
			
			$.each(appointments, function (day, data) {
				let $appointmentsGroup = magistraal.template.get('appointments-group');
				$appointmentsGroup.find('.appointments-group-title').text(capitalizeFirst(magistraal.locale.formatDate(data.time, 'ldF')));

				if(data.appointments.length == 0) {
					// No appointments on this day
					let $appointment = magistraal.template.get('appointment');
					$appointment.find('.lesson-number').html('<i class="fal fa-check"></i>');
					$appointment.attr({
						'data-type': 'none',
						'data-status': 'none',
						'data-search': '',
						'data-finishable': false,
						'data-interesting': false,
					}).removeAttr('onclick');
					$appointment.find('.lesson-designation').text(magistraal.locale.translate('appointments.none_this_day'));
					$appointment.find('.bullet').remove();
					$appointment.appendTo($appointmentsGroup);
					return true;
				}

				$.each(data.appointments, function (i, appointment) {
					let $appointment = magistraal.template.get('appointment');

					appointment.editable = (appointment.type == 'personal' || appointment.type == 'planning');

					$appointment.attr({
						'data-editable': appointment.editable,
						'data-finishable': true,
						'data-finished': appointment.finished,
						'data-has-attachments': appointment.has_attachments,
						'data-has-meeting-link': appointment.has_meeting_link,
						'data-id': appointment.id,
						'data-info-type': appointment.info_type,
						'data-interesting': (appointment.status != 'canceled'),
						'data-search': `${appointment.subjects.join(', ')} ${appointment.designation} ${appointment.content_text}`.trim(),
						'data-status': appointment.status,
						'data-type': appointment.type
					});

					if(appointment.start.lesson > 0) {
						$appointment.find('.lesson-number').text(appointment.duration.lessons <= 1 ? appointment.start.lesson: `${appointment.start.lesson}-${appointment.end.lesson}`);
					} else {
						$appointment.find('.lesson-number').html(appointment.status == 'schedule' ? '<i class="fal fa-info"></i>' : '');
					}

					if(appointment['has_meeting_link']) {
						$appointment.find('.lesson-join-ms-teams').attr('href', appointment['meeting_link']);
					}

					$appointment.find('.lesson-time').text(magistraal.locale.formatDate(appointment.start.time, 'Hi') + ' - ' + magistraal.locale.formatDate(appointment.end.time, 'Hi')); // Set designation
					$appointment.find('.lesson-designation').text(appointment.facility == '' ? appointment.designation : `${appointment.designation} (${appointment.facility})`); // Set content
					$appointment.find('.lesson-content').html(appointment['content_text']); // Set type

					let lessonType = magistraal.locale.translate(`appointments.appointment.info_type.${appointment.info_type}`);
					$appointment.find('.lesson-type').text(lessonType); // Format attachments as html

					let sidebarFeed = {
						title: appointment['designation'],
						subtitle: `${magistraal.locale.formatDate(appointment.start.time, 'Hi')} - ${magistraal.locale.formatDate(appointment.end.time, 'Hi')}`,
						table: {
							'appointment.facility': appointment.facility,
							'appointment.start': capitalizeFirst(magistraal.locale.formatDate(appointment.start.time, 'ldFYHi')),
							'appointment.end': capitalizeFirst(magistraal.locale.formatDate(appointment.end.time, 'ldFYHi')),
							'appointment.school_subject': appointment.subjects.join(', '),
						},
						actions: {}
					};

					sidebarFeed.table[`appointment.info_type.${appointment.info_type}`] = appointment.content;
					sidebarFeed.table['appointment.teachers'] = appointment.teachers.join(', ');

					if(appointment.editable) {
						sidebarFeed.actions = {
							edit: {
								handler: `magistraal.appointments.edit({id: '${appointment.id}', start: '${appointment.start.time}', end: '${appointment.end.time}', facility: '${escapeQuotes(appointment.facility)}', designation: '${escapeQuotes(appointment.designation)}', content: '${escapeQuotes(appointment.content)}'})`, 
								icon: 'fal fa-pencil'
							},
							delete: {
								handler: `magistraal.appointments.delete('${appointment.id}')`, 
								icon: 'fal fa-trash'
							}
						}
					}

					magistraal.sidebar.addFeed($appointment, sidebarFeed);

					$appointment.appendTo($appointmentsGroup);
				});

				$appointmentsGroup.appendTo($html);
			});

			magistraal.page.setContent($html);
		},

		view: (id) => {
			if($(`.appointment[data-id="${id}"]`).attr('data-has-attachments') != 'true') {
				// Don't try to load attachments if appointment doesn't have attachments
				return false;
			}

			magistraal.console.loading('console.loading.appointment_attachments');
			magistraal.api.call({
				url: 'appointments/info', 
				data: {id: id},
				callback: magistraal.appointments.viewCallback
			});
		},

		viewCallback: (appointment, source) => {
			let attachmentsHTML = '';

			if(appointment.attachments.length > 0) {
				$.each(appointment.attachments, function(i, attachment) {
					attachment.icon = magistraal.mapping.icons('file_icons', attachment.mime_type);
					attachmentsHTML += `<div class="anchor" onclick="magistraal.files.download('${attachment.location}');"><i class="${attachment.icon} icon-inline"></i>${attachment.name}.${attachment.type}</div>`;
				})
			}
			
			magistraal.sidebar.updateFeed({
				table: {
					'appointment.attachments': attachmentsHTML
				}
			}, 'appointment.end');

			if(source == 'server') {
				magistraal.console.success('console.success.appointment_attachments');
			}
		},

		finish: (id, finished) => {
			if($(`.appointment[data-id="${id}"]`).attr('data-finishable') != 'true') {
				return false;
			}

			$(`.appointment[data-id="${id}"]`).attr('data-finished', finished);

			magistraal.console.loading('console.loading.finish_appointment');

			magistraal.api.call({
				url: 'appointments/finish', 
				data: {id: id, finished: finished},
				source: 'both'
			}).then(() => {
				magistraal.console.success('console.success.finish_appointment');
			}).catch(() => {
				$(`.appointment[data-id="${id}"]`).attr('data-finished', !finished);
			});
		},

		create: (appointment, $form = null) => {
			magistraal.console.loading('console.loading.create_appointment');

			let start = new Date(appointment.date);
			start.setHours(appointment.start.hours, appointment.start.minutes, 0);
			appointment.start = start.toISOString();

			let end = new Date(appointment.date);
			end.setHours(appointment.end.hours, appointment.end.minutes, 1);
			appointment.end = end.toISOString();

			magistraal.api.call({
				url: 'appointments/create',
				data: appointment,
				source: 'server_only'
			}).then(response => {
				magistraal.console.success('console.success.create_appointment');
				magistraal.page.load('appointments/list');

				if($form) {
					$form.formReset();
				}
			}).catch(response => {
				magistraal.popup.open('appointments-create-appointment');

				if(response.responseJSON && response.responseJSON.info) {
					magistraal.console.error(`console.error.${response.responseJSON.info}`);
					return false;
				}
			})
		},

		edit: (appointment) => {
			let popup = 'appointments-create-appointment';
			let $form = magistraal.element.get('form-appointments-create-appointment');
						
			$form.find('[name="id"]').value(appointment.id);
			$form.find('[name="date"]').value(appointment.start);
			$form.find('[name="start"]').value(appointment.start);
			$form.find('[name="end"]').value(appointment.end);
			$form.find('[name="facility"]').value(appointment.facility);
			$form.find('[name="designation"]').value(appointment.designation);
			$form.find('[name="content"]').value(appointment.content);
			
			magistraal.popup.open(popup);
		},

		delete: (id) => {
			magistraal.console.loading('console.loading.delete_appointment');

			magistraal.api.call({
				url: 'appointments/delete',
				data: {id: id},
				source: 'server_only'
			}).then(response => {
				magistraal.console.success('console.success.delete_appointment');
				magistraal.page.load('appointments/list');
			}).catch(response => {
				if(response.responseJSON && response.responseJSON.info) {
					magistraal.console.error(`console.error.${response.responseJSON.info}`);
					return false;
				}
			})
		}
	},

	/* ============================ */
	/*            Files             */
	/* ============================ */
	files: {
		download: (location) => {
			magistraal.console.loading('console.loading.download');
			magistraal.api.call({
				url: 'files/download', 
				data: {location: location},
				source: 'server_only',
				callback: magistraal.files.downloadCallback,
				xhrFields: { responseType: 'arraybuffer'}
			});
		},

		downloadCallback(arrayBuffer, source, request) {
			let blob = new Blob([arrayBuffer], {type: request.getResponseHeader('Content-Type')});	
			let link = document.createElement('a');
			link.href = URL.createObjectURL(blob);
			link.download = decodeURI((request.getResponseHeader('Content-Disposition') ?? 'filename=Bestand').split('filename=')[1]);
			link.click();
			link.remove();

			magistraal.console.success('console.success.download');
		}
	},

	/* ============================ */
	/*            Grades            */
	/* ============================ */
	grades: {
		paintList: (grades, source, request) => {
			let $html = $('<div></div>');
			
			$.each(grades, function (i, grade) {
				let $grade    = magistraal.template.get('grade-list-item');
				let enteredAt = magistraal.locale.formatDate(grade['entered_at'], 'dFYHi');

				$grade.attr({
					'data-counts': grade['counts'],
					'data-exemption': grade['exemption'],
					'data-interesting': true,
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

				$grade.appendTo($html);
			});

			magistraal.page.setContent($html);
		}
	},

	/* ============================ */
	/*           Messages           */
	/* ============================ */
	messages: {
		paintList: (messages, source) => {
			/* Pre-load newest three messages while painting */
			magistraal.api.call({url: 'messages/info', data: {id: String(messages[0].id)}, source: 'prefer_cache'});
			magistraal.api.call({url: 'messages/info', data: {id: String(messages[1].id)}, source: 'prefer_cache'});
			magistraal.api.call({url: 'messages/info', data: {id: String(messages[2].id)}, source: 'prefer_cache'});

			let $html = $('<div></div>');

			$.each(messages, function (i, message) {
				let $message = magistraal.template.get('message-list-item');
				message.subject = message.subject || magistraal.locale.translate('messages.subject.no_subject');
				$message.find('.message-list-item-title').text(message.subject);
				$message.find('.message-list-item-side-title').text();
				$message.find('.message-list-item-content').text(message.sender.name);
				$message.attr({
					'data-id': message.id,
					'data-interesting': true,
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

				$message.appendTo($html);
			});

			magistraal.page.setContent($html);
		},

		view: (id, read = true) => {
			magistraal.console.loading('console.loading.message_content');
			magistraal.api.call({
				url: 'messages/info', 
				data: {id: id},
				callback: magistraal.messages.viewCallback,
				source: 'prefer_cache'
			});

			if(read != true) {
				magistraal.api.call({
					url: 'messages/info', 
					data: {id: id, read: true}
				});
			}
		},

		viewCallback: (message, source) => {
			let attachmentsHTML = '';

			if(message.attachments.length > 0) {
				$.each(message.attachments, function(i, attachment) {
					attachment.icon = magistraal.mapping.icons('file_icons', attachment.mime_type);
					attachmentsHTML += `<div class="anchor" onclick="magistraal.files.download('${attachment.location}');"><i class="${attachment.icon} mr-1"></i>${attachment.name}.${attachment.type}</div>`;
				})
			}

			let updateFeedWith = {
				'table': {
					'message.to': message.recipients.to.names.join(', '),
					'message.cc': message.recipients.cc.names.join(', '),
					'message.bcc': message.recipients.bcc.names.join(', '),
					'message.attachments': attachmentsHTML,
					'message.content': message.content
				}
			};
			
			magistraal.sidebar.updateFeed(updateFeedWith, undefined);

			if(source == 'server') {
				magistraal.console.success('console.success.message_content');
			}
		},

		send: (message, $form = null) => {
			magistraal.console.loading('console.loading.send_message');

			magistraal.api.call({
				url: 'messages/send', 
				data: message,
				source: 'server_only'
			}).then(response => {
				magistraal.console.success('console.success.send_message');
				magistraal.page.load('messages/list');

				if($form) {
					$form.formReset();
				}
			}).catch(response => {
				magistraal.popup.open('messages-write-message');

				magistraal.console.error(`console.error.${response?.responseJSON?.info}`);
			})
		}
	},

	/* ============================ */
	/*           Settings           */
	/* ============================ */
	settings: {
		paintList: settings => {
			let $html = $('<div></div>');

			$.each(settings?.items, function (itemNamespace, item) {
				let $item = $('<div></div>');

				if(typeof item.items != 'undefined') {
					// Item is a category
					$item = magistraal.template.get('setting-category');
					$item.find('.setting-category-title').text(magistraal.locale.translate(`settings.category.${settings.category}.${itemNamespace}.title`));
					$item.find('.setting-category-icon').html(`<i class="${(item === null || item === void 0 ? void 0 : item.icon) || 'cog'}"></i>`); // Create content description which consists of the children's names of this items

					let content = '';
					$.each(item.items, function (childItemNamespace, childItem) {
						if(typeof childItem.items == 'undefined') {
							// Child item is a setting
							content += magistraal.locale.translate(`settings.setting.${itemNamespace}.${childItemNamespace}.title`) + ', ';
						} else {
							content += magistraal.locale.translate(`settings.category.${itemNamespace}.${childItemNamespace}.title`) + ', ';
						}
					});
					content = content.slice(0, -2); // Remove last ', ' from string

					$item.find('.setting-category-content').text(content);
					$item.attr('onclick', `magistraal.page.load('settings/list', {'category': '${itemNamespace}'});`);
				} else if(typeof item.values != 'undefined') {
					// Item is a setting
					$item        = magistraal.template.get('setting');
					let $input   = $('<input class="form-control input-search">')
					$input.appendTo($item);
					
					// Remap dark_auto and light_auto theme to auto
					if(typeof item.value != 'undefined' && (item.value == 'dark_auto' || item.value == 'light_auto')) {
						item.value = 'auto';
					}

					let inputObj = new magistraal.inputs.searchInput($input);
					let values   = [];

					$input.val(magistraal.locale.translate(`settings.setting.${settings.category}.${itemNamespace}.value.${item?.value || item?.default}.title`));
					$input.value(item?.value || item?.default);

					$.each(item.values, function(value, info) {
						let title = magistraal.locale.translate(`settings.setting.${settings.category}.${itemNamespace}.value.${info?.title}.title`);
						
						values.push({
							title: title,
							value: value,
							icon: info?.icon,
							description: info?.description || title
						});
					})

					inputObj.results.set(values);

					$item.attr({
						'data-setting': `${settings?.category}.${itemNamespace}`,
						'data-reload': item?.options?.reload || false
					})
					$item.find('.setting-title').text(magistraal.locale.translate(`settings.setting.${settings.category}.${itemNamespace}.title`));
				}

				$item.appendTo($html);
			});

			magistraal.page.setContent($html);
		},

		updateClient: (settings, updateOnServer = false) => {
			if(typeof settings['appearance.theme'] != 'undefined' && settings['appearance.theme'].includes('auto')){
				if(window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
					settings['appearance.theme'] = 'dark_auto';
				} else {
					settings['appearance.theme'] = 'light_auto';
				}
			}

			magistraalPersistentStorage.set('settings', settings);

			if(updateOnServer) {
				magistraal.settings.set_all(settings);
			}

			let settingsString = '';
			$.each(settings, function(key, value) {
				settingsString += `${key}=${value},`
			})

			$('body').attr('data-settings', trim(settingsString, ','));
		},

		get_all: () => {
			return new Promise((resolve, reject) => {
				magistraal.api.call({
					url: 'user/settings/get_all'
				}).then(data => {
					resolve(data);
				}).catch(data => {
					magistraal.console.error();
					reject(data);
				})
			})
		},

		set: (setting, value) => {
			magistraal.console.loading('console.loading.save');

			return new Promise((resolve, reject) => {
				magistraal.api.call({
					url: 'user/settings/set',
					data: {setting: setting, value: value},
					source: 'server_only'
				}).then(data => {
					magistraal.settings.updateClient(data);

					magistraal.console.success('console.success.save');

					resolve();
				}).catch(data => {
					magistraal.console.error();
					reject();
				})
			})
		},

		set_all: settings => {
			magistraal.console.loading('console.loading.save');
			
			return new Promise((resolve, reject) => {
				magistraal.api.call({
					url: 'user/settings/set_all',
					data: {settings: settings},
					source: 'server_only'
				}).then(data => {
					magistraal.settings.updateClient(data);

					magistraal.console.success('console.success.save');

					resolve();
				}).catch(data => {
					magistraal.console.error();
					reject();
				})
			})
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
			message = magistraal.locale.translate(message, magistraal.locale.translate(`console.${type}.generic`, message));
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

			if(duration >= 0) {
				setTimeout(() => {
					magistraal.element.get(`console-message-${messageId}`).remove();
				}, duration);
			}

			return messageId;
		}
	},

	load: (parameters = {}) => {
		magistraalStorage.set('api', '/magistraal/api/');

		if(typeof parameters.version == 'undefined') {
			return false;
		}

		// If current version is not equal to new one, soft-clear cache
		if(magistraalPersistentStorage.get('version') != parameters?.version) {
			console.log(`New version (${parameters?.version}) was found!`);
			magistraalPersistentStorage.clear(true);
		}

		magistraalStorage.set('version', parameters?.version);
		magistraalPersistentStorage.set('version', parameters?.version);

		return new Promise((resolve, reject) => {
			magistraal.locale.load('nl_NL').then(() => {
				$(document).trigger('magistraal.ready');
				resolve();
			}).catch(() => {});

			if(parameters?.doPreCache != 'false') {
				// Pre-load absences, appointments, grades, messages, etc. for offline use
				magistraal.api.call({url: 'absences/list', source: 'prefer_cache'});
				magistraal.api.call({url: 'appointments/list', source: 'prefer_cache'});
				magistraal.api.call({url: 'grades/list', source: 'prefer_cache'});
				magistraal.api.call({url: 'messages/list', source: 'prefer_cache'});
				magistraal.api.call({url: 'settings/list', source: 'prefer_cache'});
			}
		});
	},

	locale: {
		load: locale => {
			return new Promise((resolve, reject) => {
				magistraal.api.call({
					url: 'locale', 
					data: {locale: locale},
					source: 'both',
					callback: magistraal.locale.loadCallback
				}).finally(() => {
					resolve();
				}).catch(() => {});
			});
		},

		loadCallback: (localeData, source) => {
			magistraalStorage.set('translations', localeData);
			$('[data-translation]').each(function () {
				if(this.tagName.toLowerCase() === 'input' || this.tagName.toLowerCase() === 'textarea') {
					// Set placeholder on input or textarea elements
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

			if(typeof translations == 'undefined' || typeof translations[key] == 'undefined') {
				return fallback;
			}

			return translations[key];
		},

		formatBoolean: boolean => {
			if(boolean == '1' || boolean == 'yes' || boolean == 'true') {
				return magistraal.locale.translate('generic.bool.true', 'true');
			} else if(boolean == '0' || boolean == 'no' || boolean == 'false') {
				return magistraal.locale.translate('generic.bool.false', 'false');
			} else {
				return '';
			}
		},

		formatDate: (date, format) => {
			if(typeof date == 'number') {
				// Convert unix to date object
				date = new Date(date * 1000);
			} else if(typeof date == 'string') {
				// Convert ISO to date object
				date = new Date(date);
			}

			if(typeof date != 'object') {
				return false;
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
			if(magistraalStorage.get('nav_active') == 'true') {
				magistraal.nav.close();
			} else {
				magistraal.nav.open();
			}
		}
	},

	page: {
		load: (page, data = {}, cachable = true) => {
			page = trim(page.replace(/[^a-zA-Z\/]/g, ''), '/');

			if(page == 'login' || page == 'main') {
				window.location.href = `../${page}/`;
				return true;
			} else if(page == 'logout') {
				magistraal.logout.logout();
			}

			history.pushState(null, null, window.location.pathname + '#' + page + '/' + random(1000, 9999));
			
			magistraal.sidebar.clearFeed();
			magistraal.sidebar.close();
			magistraal.element.get('main').scrollTop(0);
			$('.nav-item').removeClass('active');
			magistraal.element.get(`nav-item-${page}`).addClass('active');
			
			magistraal.page.get(page, data, cachable);
		},

		get: (page, data = {}, cachable = true) => {
			magistraal.element.get('page-search').val('');
			page = trim(page, '/');

			let callbacks = {
				'absences/list': magistraal.absences.paintList,
				'appointments/list': magistraal.appointments.paintList,
				'grades/list': magistraal.grades.paintList,
				'messages/list': magistraal.messages.paintList,
				'logout': magistraal.logout.logout,
				'settings/list': magistraal.settings.paintList
			};

			let callback = undefined
			if(typeof callbacks[page] != 'undefined') {
				callback = callbacks[page];
			}

			let $pageButtonsTemplate = magistraal.template.get(`page-buttons-${page}`);
			let $pageButtonsContainer = magistraal.element.get('page-buttons-container');

			if($pageButtonsTemplate.length > 0) {
				$pageButtonsContainer.html($pageButtonsTemplate.html());
			} else {
				$pageButtonsContainer.html('');
			}

			$('body').attr('data-page-buttons', $pageButtonsContainer.find('.btn').length); // Change page title

			magistraal.element.get('page-title').text(magistraal.locale.translate(`page.${page}.title`));
			return new Promise((resolve, reject) => {
				try {
					magistraal.page.request(page, data, callback, cachable).then(response => {
						resolve(response);
					}).catch(() => {});
				} catch {
					magistraal.console.error();
				}
			});
		},
		
		request: (page, data = {}, callback = null, cachable = true) => {
			return new Promise((resolve, reject) => {
				magistraal.console.loading('console.loading.refresh');
				magistraal.api.call({
					url: page, 
					data: data, 
					source: 'both',
					cachable: cachable, 
					callback: function(data, source, request){callback(data, source, request);magistraal.page.requestCallback(data, source, request);}, 
					scope: page
				}).then(response => {
					resolve(response);
				}).catch(response => {
					reject(response);
				});
			});
		},

		requestCallback: (data, source, request) => {
			// Selecteer eerste item
			let $li_first = magistraal.element.get('main').find('.list-item[data-interesting="true"]').first();
			magistraal.sidebar.selectFeed($li_first, false);

			if(source == 'server') {
				magistraal.console.success('console.success.refresh');
			}
		},

		current: () => {
			return trim(window.location.hash.substring(1).replace(/[^a-zA-Z\/]/g, ''), '/');
		},
		
		setContent: ($html) => {
			$('main').empty().append($html.children());
		}
	},
	login: {
		login: ($form) => {
			let action = $form.attr('action');

			magistraal.console.loading('console.loading.login');

			magistraal.api.call({
				url: action, 
				data: $form.serialize(),
				source: 'server_only',
			}).then(response => {
				window.location.href = '../main/';
			}).catch(response => {
				magistraal.console.error(`console.error.${response?.responseJSON?.info}`);
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
				if(this.$input.closest('.input-wrapper').length == 0) {
					this.$input.wrap('<div class="input-wrapper"></div>');
				}

				this.$wrapper = this.$input.closest('.input-wrapper');
				this.$wrapper.addClass('input-tags-wrapper');
				this.$tags = $('<ul class="input-tags-list"></ul>');
				this.$tags.appendTo(this.$wrapper); // Create ghost input

				if(this.$wrapper.hasClass('input-search-wrapper')) {
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
				if(typeof e.addTag != undefined) {
					var _e$addTag, _e$addTag2;

					this.$input.addTag((_e$addTag = e.addTag) === null || _e$addTag === void 0 ? void 0 : _e$addTag.value, (_e$addTag2 = e.addTag) === null || _e$addTag2 === void 0 ? void 0 : _e$addTag2.text);
				}
			}

			eventKeyup(e) {
				if(e.which == 8) {
					// Backspace, remove last tag
					let lastTagValue = this.$tags.find('.input-tags-tag:last-of-type').attr('value');
					this.$input.removeTag(lastTagValue);
				}
			}

		},
		searchInput: class searchInput {
			constructor($input) {
				this.$input = $input;
				this.setup();
			}

			setup() {
				if(this.$input.closest('.input-wrapper').length == 0) {
					this.$input.wrap('<div class="input-wrapper"></div>');
				}

				this.$input.data('input', this);

				this.$wrapper = this.$input.closest('.input-wrapper');
				this.$wrapper.addClass('input-search-wrapper');
				this.$results = $('<ul class="input-search-results"></ul>');
				this.$results.appendTo(this.$wrapper);
				if(!this.$input.attr('placeholder')) {
					this.$input.attr('placeholder', magistraal.locale.translate('generic.action.search'));
				}

				this.$input.on('click', e => {
					this.eventFocus(e);
				});

				this.$input.on('focusout', e => {
					this.eventFocusOut(e);
				});
				
				if(typeof this.$input.attr('data-magistraal-search-api') == 'undefined') {
					this.$input.on('input', e => {
						this.eventInput(e);
					});
					this.$input.attr('data-magistraal-search-target', 'magistraal-input-results')
				} else {
					this.$input.on('input', debounce(e => {
						this.eventInput(e);
					}, 250));
				}

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

			eventInput(e) {
				if(typeof this.$input.attr('data-magistraal-search-api') != 'undefined') {
					// Fetch data from api
					let api = this.$input.attr('data-magistraal-search-api');
					let query = this.$input.val() || this.$input.text();
					magistraal.api.call({
						url: `${api}/search`,
						data: {query: query},
						cachable: false
					}).then(response => {
						let results = magistraal.inputs.search.remap_api_response(api, response);
						this.results.set(results);
					}).catch(err => {
						console.error(err);
					});
				}
			}

			eventResultClick(e) {
				let $result = $(e.target).closest('.input-search-result');
				let value = $result.attr('value');
				let text = $result.find('.input-search-result-title').text() || $result.text();
				this.$input.val('');

				if(this.$wrapper.hasClass('input-tags-wrapper')) {
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
						this.$wrapper.addClass('active');
						this.$wrapper.find('.input-ghost')?.addClass('focus');
					}, 50);
				} else {
					this.$input.val(text).attr('data-value', value);
					this.$input.trigger('magistraal.change');
				}
			}
			
			results = {
				set: (results) => {
					let html = '';

					$.each(results, function (i, result) {
						if(typeof result.icon == 'undefined' || typeof result.description == 'undefined') {
							html += `<li class="input-search-result" value="${result?.value}"><span class="input-search-result-title">${result?.title}</span></li>`;
						} else {
							html += `<li class="input-search-result input-search-result-rich" value="${result?.value}"><i class="input-search-result-icon ${result?.icon}"></i><span class="input-search-result-title">${result?.title}</span><span class="input-search-result-description">${result?.description}</span></li>`;
						}
					});
					
					this.$results.html(html);
				}
			}
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
			magistraal.page.request('logout', {}).finally(() => {
				magistraalPersistentStorage.clear();
				magistraal.page.load('login');
			});
		}
	},
	sidebar: {
		addFeed: ($elem = null, feed) => {
			feed.title    = feed?.title || '';
			feed.subtitle = feed?.subtitle || '';
			feed.table    = feed?.table || {};
			feed.actions  = feed?.actions || {};
			$elem = $elem || magistraal.element.get('sidebar');
			$elem.attr('data-sidebar-feed', encodeURI(JSON.stringify(feed)));
			return true;
		},

		clearFeed: () => {
			return magistraal.sidebar.selectFeed(null);
		},

		getFeed: ($elem = null) => {
			$elem = $elem || magistraal.element.get('sidebar');
			return JSON.parse(decodeURI($elem.attr('data-sidebar-feed') || '{}'));
		},

		selectFeed: ($elem, openSidebar = true) => {
			if($elem == null) {
				return magistraal.sidebar.setFeed(undefined, false);
			}

			$(`[class="${$elem.attr('class')}"]`).removeAttr('data-selected');
			$elem.attr('data-selected', true);
			magistraal.sidebar.setFeed(magistraal.sidebar.getFeed($elem), openSidebar);
		},

		setFeed: (feed = {title: '', subtitle: '', table: {}, actions: {}}, openSidebar = true) => {
			let $sidebarTable   = magistraal.element.get('sidebar-table');
			let $sidebarActions = magistraal.element.get('sidebar-actions');

			magistraal.element.get('sidebar-title').text(feed.title);
			magistraal.element.get('sidebar-subtitle').text(feed.subtitle);

			$sidebarTable.empty();
			$sidebarActions.empty();

			magistraal.sidebar.addFeed(undefined, feed);

			$.each(feed.table, function (tableKey, tableValue) {
				if(tableKey == '' || tableValue == '') {
					return true;
				}

				let $tableKey = magistraal.template.get('sidebar-table-key');
				$tableKey.attr('data-key', tableKey).text(magistraal.locale.translate(`sidebar.table.info.${tableKey}`));
				$tableKey.appendTo($sidebarTable);
				let $tableValue = magistraal.template.get('sidebar-table-value');
				$tableValue.html(tableValue);
				$tableValue.appendTo($sidebarTable);
			});

			$.each(feed.actions, function(actionType, action) {
				let $action     = magistraal.template.get('sidebar-action');
				let actionColor = (actionType == 'delete' ? 'danger' : 'secondary');
				$action.addClass(`btn-${actionColor}`);

				$action.html(`
					<i class="${action?.icon} btn-icon"></i>
					<span class="btn-text">${magistraal.locale.translate(`generic.action.${actionType}`)}</span>
				`);
				
				$action.attr({'data-action': actionType, 'onclick': action?.handler});
				$action.appendTo($sidebarActions);
			})

			if(openSidebar) {
				setTimeout(() => {
					magistraal.sidebar.open();
				}, 50);
			}
		},

		updateFeed: (updateFeedWith, insertAfterKey = undefined) => {
			let newFeed                = {title: '', subtitle: '', table: {}, actions: {}};
			let currentFeed            = magistraal.sidebar.getFeed();
			let currentFeedTableLength = Object.keys(currentFeed.table).length;
			
			newFeed.title = updateFeedWith?.title || currentFeed.title; 
			newFeed.subtitle = updateFeedWith?.subtitle || currentFeed.subtitle; 

			let i = 1;
			$.each(currentFeed.table, function(currentTableKey, currentTableValue) {
				newFeed.table[currentTableKey] = currentTableValue;

			    if(currentTableKey == insertAfterKey || i == currentFeedTableLength) {
					$.each(updateFeedWith.table, function(updateTableKey, updateTableValue) {
						newFeed.table[updateTableKey] = updateTableValue;
					})
				}

				i++;
			})

			newFeed.actions = currentFeed.actions;

			magistraal.sidebar.setFeed(newFeed, false);
		},

		currentFeed() {
			let $sidebar = magistraal.element.get('sidebar');
			return JSON.parse(decodeURI($sidebar.attr('data-sidebar-feed') || '{}'));
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
			if(magistraalStorage.get('sidebar_active') == 'true') {
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

			if($popup.length === 0) {
				return false;
			}

			magistraal.element.get('popup-backdrop').addClass('show');
			magistraal.popup.enable(selector);
			$popup.addClass('show');
		},

		close: selector => {
			let $popup = magistraal.popup.get(selector);

			if($popup.length === 0) {
				return false;
			}

			magistraal.element.get('popup-backdrop').removeClass('show');
			magistraal.popup.disable(selector);
			$popup.removeClass('show');
		},

		enable: selector => {
			let $popup = magistraal.popup.get(selector);

			$popup.find('[data-popup-action]').removeAttr('disabled');
		},

		disable: selector => {
			let $popup = magistraal.popup.get(selector);

			$popup.find('[data-popup-action]').attr('disabled', 'disabled');
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
	},

	token: {
		isSet: () => {
			return (getCookie('magistraal-authorization') != '');
		},
		delete: () => {
			setCookie('magistraal-authorization', null, -1);
			return true;
		}
	},
	
	mapping: {
		icons(category, selector) {
			switch(category) {
				case 'file_icons':
					if(selector == 'application/msword' || selector == 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') {
						return 'fal fa-file-word';
					} else if(selector == 'application/vnd.ms-powerpoint' || selector == 'application/vnd.openxmlformats-officedocument.presentationml.presentation') {
						return 'fal fa-file-powerpoint';
					} else if(selector == 'application/pdf') {
						return 'fal fa-file-pdf';
					} else if(selector.includes('image/')) {
						return 'fal fa-file-image';
					} else if(selector.includes('video/')) {
						return 'fal fa-file-video';
					} else if(selector.includes('audio/')) {
						return 'fal fa-file-audio';
					} else if(selector.includes('text/')) {
						return 'fal fa-file-alt';
					} else if(selector == 'application/zip' || selector == 'application/x-zip-compressed' || selector == 'application/x-7z-compressed' || selector == 'application/vnd.rar' || selector == 'application/x-bzip' || selector == 'application/x-bzip2') {
						return 'fal fa-file-archive';
					} else {
						return 'fal fa-file';
					}
			}

			return 'fal fa-question';
		}
	}
};