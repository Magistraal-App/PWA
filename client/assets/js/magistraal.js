var _magistraalStorage = {};

const magistraalStorage = {
	get: key => {
		const result = _magistraalStorage[key] ?? undefined;

		if(!isSet(result)) {
			return {value: undefined, storedAt: Date.now()};
		}

		return result;
	},

	set: (key, value) => {
		const result = {value: value, storedAt: Date.now()};
		_magistraalStorage[key] = result;
		return true;
	},

	remove: (key) => {
		if(isSet(_magistraalStorage[key])) {
			delete magistraalStorage[key];
		}

		return true;
	}
};

const magistraalPersistentStorage = {
	get: key => {
		try {
			const result = JSON.parse(localStorage.getItem(`magistraal.${key}`));

			if(!isSet(result)) {
				return {value: undefined, storedAt: Date.now()};
			}

			return result;
		} catch(err) {
			return {value: undefined, storedAt: Date.now()};
		}
	},

	set: (key, value) => {
		const result = {value: value, storedAt: Date.now()};
		return localStorage.setItem(`magistraal.${key}`, JSON.stringify(result));
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

			if(key == 'magistraal.user_uuid' && soft) {
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
			if(!isSet(parameters.callback ))        { parameters.callback = function() {}; }
			if(!isSet(parameters.source))           { parameters.source = 'both'; }
			if(!isSet(parameters.scope))            { parameters.scope = magistraal.page.current(); }
			if(!isSet(parameters.url))              { return false; }
			if(!isSet(parameters.data))             { parameters.data = {}; }
			if(!isSet(parameters.cachable))         { parameters.cachable = (parameters.source != 'server_only'); }
			if(!isSet(parameters.xhrFields))        { parameters.xhrFields = {}; }
			if(!isSet(parameters.alwaysReturn))     { parameters.alwaysReturn = {}; }
			if(!isSet(parameters.cacheMaxLifetime)) { parameters.cacheMaxLifetime = 3*24*60*60; }

			return new Promise((resolve, reject) => {
				if(parameters.cachable) {
					// Laad response voor uit cache, maar alleen als er een callback is opgegeven
					const cache = magistraalPersistentStorage.get(`api_response.${parameters.url}.${JSON.stringify(parameters.data)}`);
					const cachedResponse = cache.value;

					if(isSet(cachedResponse)) {
						// Beantwoord request met response uit cache als deze niet te oud is
						if(parameters.source == 'prefer_cache' && (Date.now() - cache.storedAt) <= parameters.cacheMaxLifetime*1000) {
							// Stuur geen meer request naar de server
							try {
								resolve(cachedResponse);
								if(typeof parameters.callback == 'function') {
									parameters.callback(cachedResponse, 'cache_final', undefined, parameters.url);
								}

								return;
							} catch(err) {
								console.error('An error occured with cached response:', err);
								magistraal.console.error();
							}
						} else {
							// Stuur nog wel een request naar de server
							try {
								resolve(cachedResponse);
								if(typeof parameters.callback == 'function') {
									parameters.callback(cachedResponse, 'cache_pre', undefined, parameters.url);
								}
							} catch(err) {
								console.error('An error occured with cached response:', err);
								magistraal.console.error();
							}
						}
					}
				}

				// Stuur cookies mee
				parameters.xhrFields.withCredentials = true;

				// Verstuur request naar de server
				$.ajax({
					method: 'POST',
					url: `${magistraalStorage.get('api').value}${parameters.url}/`,
					data: parameters.data,
					headers: {'Accept': '*/*'},
					xhrFields: parameters.xhrFields,
					cache: false,
					success: function(response, textStatus, request) {
						// Als de pagina niet meer hetzelfde is, verwerp de response
						if(isSet(parameters.scope) && parameters.scope != magistraal.page.current()) {
							return;
						}

						// Als de request gelukt is
						if(isSet(response.success) && response.success === true && isSet(response.data)) {
							// Sla de response op in de cache
							if(parameters.cachable) {
								magistraalPersistentStorage.set(`api_response.${parameters.url}.${JSON.stringify(parameters.data)}`, response);
							}

							// Voer callback uit met response van server
							try {
								resolve(response);
								if(typeof parameters.callback == 'function') {
									parameters.callback(response, 'server_final', request, parameters.url);
								}
							} catch(err) {
								console.error('An error occured with succesful live response from:', err);
								magistraal.console.error();
							}
						} else {
							try {
								if(parameters.alwaysReturn) {
									resolve(response);
									if(typeof parameters.callback == 'function') {
										parameters.callback(response, 'server_final', request, parameters.url);
									}
								}
							} catch(err) {
								console.error('An error occured with failed live response from:', err);
								magistraal.console.error();
							}
						}
					},

					error: function(response) {
						// Laat de gebruiker opnieuw inloggen als token niet bestaat / onjuist is
						if(isSet(response.responseJSON) && isSet(response.responseJSON.info) && response.responseJSON.info.includes('token_invalid')) {
							magistraal.token.delete();
							magistraal.page.load({
								page: 'login'
							});
							return;
						}

						// Verwerp de response als de pagina niet meer hetzelfde is
						if(isSet(parameters.scope) && parameters.scope != magistraal.page.current()) {
							return;
						}

						// Als de gebruiker offline is
						if(response.readyState == 0 && response.status == 0) {
							console.log('offline:', true);

							const cachedResponse = magistraalPersistentStorage.get(`api_response.${parameters.url}.${JSON.stringify(parameters.data)}`).value;
							
							if(isSet(cachedResponse)) {
								// Voer callback uit met response van cache
								try {
									resolve(cachedResponse);
									if(typeof parameters.callback == 'function') {
										parameters.callback(cachedResponse, 'cache_final', undefined, parameters.url);
									}
								} catch(err) {
									console.error('An error occured with cached response:', err);
									magistraal.console.error();
								}

								console.info('bgw om ' + magistraal.locale.formatDate(cachedResponse.storedAt, 'Hi'));
								return;
							}
						} else {
							// Beantwoord de promise alleen als de response geschikt is om verder te gebruiken
							if((isSet(response.responseJSON) && isSet(response.responseJSON.info)) || parameters.alwaysReturn) {
								reject(response);
								if(typeof parameters.callback == 'function') {
									parameters.callback(response, 'cache_final', undefined, parameters.url);
								}
							} else {
								magistraal.console.error();
								console.error(response);
							}
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
		paintList: response => {
			let $html = $('<div></div>');

			$.each(response.data, function (month, data) {
				// Ga naar volgende maand als er geen absenties voor deze maand zijn
				if(data.absences.length == 0) {
					return true;
				}

				// Maak een groep en stel de titel in
				let $absencesGroup = magistraal.template.get('absences-group');
				$absencesGroup.find('.absences-group-title').text(capitalizeFirst(magistraal.locale.formatDate(data.time, 'Fy')));
				
				// Ga alle absenties bij langs
				$.each(data.absences, function (i, absence) {
					// Maak een absentie
					let $absence = magistraal.template.get('absence-list-item');

					// Informatie
					$absence.find('.list-item-title').html(absence.appointment.description + '<span class="bullet"></span>' + magistraal.locale.formatDate(absence.appointment.start.time, 'Hi') + ' - ' + magistraal.locale.formatDate(absence.appointment.end.time, 'Hi'));
					$absence.find('.list-item-icon').text(absence.lesson || absence.abbr);
					$absence.find('.list-item-content').text(absence.description);
					
					// Attributen
					$absence.attr({
						'data-interesting': true,
						'data-permitted': absence.permitted,
						'data-search': absence.description,
						'data-type': absence.type
					});

					// Maak een sidebar feed
					magistraal.sidebar.addFeed($absence, {
						'title': absence.appointment.description,
						'subtitle': absence.description,
						'table': {
							'absence.date': capitalizeFirst(magistraal.locale.formatDate(absence.appointment.start.time, 'ldFY')),
							'absence.time': magistraal.locale.formatDate(absence.appointment.start.time, 'Hi') + ' - ' + magistraal.locale.formatDate(absence.appointment.end.time, 'Hi'),
							'absence.lesson': absence.lesson,
							'absence.permitted': magistraal.locale.formatBoolean(absence.permitted)
						}
					});

					// Voeg de asbentie toe aan de groep
					$absence.appendTo($absencesGroup);
				});

				// Voeg de groep toe aan de inhoud
				$absencesGroup.appendTo($html);
			});
			
			// Werk de inhoud bij
			magistraal.page.setContent($html);
		}
	},

	/* ============================ */
	/*         Appointments         */
	/* ============================ */
	appointments: {
		paintList: response => {
			let $html = $('<div><div class="horizontal-scroll-wrapper"></div></div>');
			
			$.each(response.data, function (day, data) {
				// Maak een groep en stel de titel in
				let $appointmentsGroup = magistraal.template.get('appointments-group');
				$appointmentsGroup.find('.appointments-group-title').text(capitalizeFirst(magistraal.locale.formatDate(data.time, 'ldF')));

				// Ga naar de volgende dag ls er geen afspraken op deze dag zijn
				if(data.appointments.length == 0) {
					return true;
				}

				// Ga alle afspraken bij langs
				$.each(data.appointments, function (i, appointment) {
					// Maak een afspraak
					let $appointment = magistraal.template.get('appointment');

					// Bepaal of gebruiker de afspraak kan beheren
					appointment.editable = (appointment.type == 'personal' || appointment.type == 'planning');

					// Attributen
					$appointment.attr({
						'data-editable': appointment.editable,
						'data-finishable': true,
						'data-finished': appointment.finished,
						'data-has-attachments': appointment.has_attachments,
						'data-has-meeting-link': appointment.has_meeting_link,
						'data-meeting-link': appointment.meeting_link,
						'data-id': appointment.id,
						'data-info-type': appointment.info_type,
						'data-interesting': (appointment.status != 'canceled'),
						'data-search': `${appointment.subjects.join(', ')} ${appointment.description} ${appointment.content_text}`.trim(),
						'data-status': appointment.status,
						'data-type': appointment.type
					});

					// Pictogram
					if(appointment.start.lesson > 0) {
						// 'startuur' of 'einduur-startuur'
						$appointment.find('.list-item-icon').text(
							appointment.duration.lessons <= 1 
								? appointment.start.lesson
								: `${appointment.start.lesson}-${appointment.end.lesson}`
						);
					} else {
						$appointment.find('.list-item-icon').html('<i class="fal fa-info"></i>');
					}

					// Microsoft Teams link
					// if(appointment['has_meeting_link']) {
					// 	$appointment.find('.lesson-join-ms-teams').attr('href', appointment['meeting_link']);
					// }

					// Informatie (tijd, omschrijving en inhoud)
					$appointment.find('.appointment-time').text(magistraal.locale.formatDate(appointment.start.time, 'Hi') + ' - ' + magistraal.locale.formatDate(appointment.end.time, 'Hi'));
					$appointment.find('.appointment-description').text(appointment.facility == '' ? appointment.description : `${appointment.description} (${appointment.facility})`);
					$appointment.find('.list-item-content').html(appointment['content_text']); // Set type

					// Infotype
					const infoTypeTranslation = magistraal.locale.translate(`appointments.appointment.info_type.${appointment.info_type}`);
					$appointment.find('.list-item-action-primary').text(infoTypeTranslation); 
					
					// Maak een sidebar feed
					let sidebarFeed = {
						title: appointment['description'],
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
					
					// Voeg verwijder- en bewerkknop toe aan de sidebar feed als de afspraak kan worden bewerkt
					if(appointment.editable) {
						sidebarFeed.actions.delete = {
							handler: `magistraal.appointments.delete('${appointment.id}')`, 
							icon: 'fal fa-trash'
						}

						sidebarFeed.actions.edit = {
							handler: `magistraal.appointments.edit(${JSON.stringify(appointment)})`, 
							icon: 'fal fa-pencil-alt'
						}
					}

					// Voeg knop om af te ronden toe aan de sidebar feed
					sidebarFeed.actions.finish = {
						handler: `magistraal.appointments.finish('${appointment.id}', $('.appointment-list-item[data-id="${appointment.id}"]').attr('data-finished') != 'true');`,
						icon: 'fal fa-check'
					}

					// Voeg meeting link toe aan de sidebar feed
					if(appointment.has_meeting_link) {
						sidebarFeed.actions.join_meeting = {
							handler: `magistraal.appointments.joinMeeting('${appointment.meeting_link}');`,
							icon: 'fal fa-users'
						}
					}

					// Voeg de sidebar feed toe aan de afspraak
					magistraal.sidebar.addFeed($appointment, sidebarFeed);

					// Voeg de afspraak toe aan de groep
					$appointment.appendTo($appointmentsGroup);
				});

				// Voeg de groep toe aan de inhoud
				$appointmentsGroup.appendTo($html.find('.horizontal-scroll-wrapper'));
			});

			// Werk de inhoud bij
			magistraal.page.setContent($html);
		},

		view: (id) => {
			if($(`.appointment-list-item[data-id="${id}"]`).attr('data-has-attachments') != 'true') {
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

		viewCallback: (appointment, loadType) => {
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

			if(loadType == 'server_final') {
				magistraal.console.success('console.success.appointment_attachments');
			}
		},

		joinMeeting: (meeting) => {
			window.open(meeting, '_blank', 'noopener');
		},

		finish: (id, finish) => {
			const $appointment = $(`.appointment-list-item[data-id="${id}"]`);

			if($appointment.attr('data-finishable') != 'true') {
				return false;
			}

			$appointment.attr('data-finished', finish);

			magistraal.console.loading(finish ? 'console.loading.finish_appointment' : 'console.loading.unfinish_appointment');

			magistraal.api.call({
				url: 'appointments/finish', 
				data: {id: id, finished: finish},
				source: 'both'
			}).then(() => {
				magistraal.console.success(finish ? 'console.success.finish_appointment' : 'console.success.unfinish_appointment');
			}).catch(() => {
				$(`.appointment-list-item[data-id="${id}"]`).attr('data-finished', !finish);
			});
		},

		create: (appointment, $form = undefined) => {
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
			}).then(data => {
				magistraal.console.success('console.success.create_appointment');
				magistraal.page.load({
					page: 'appointments/list'
				});

				if($form) {
					$form.formReset();
				}
			}).catch(response => {
				magistraal.popup.open('appointments_create_appointment');

				if(response.responseJSON && response.responseJSON.info) {
					magistraal.console.error(`console.error.${response.responseJSON.info}`);
					return false;
				}
			})
		},

		edit: (appointment) => {
			const popup = 'appointments_create_appointment';
			const $form = magistraal.element.get('form-appointments_create_appointment');
						
			$form.find('[name="id"]').value(appointment.id);
			$form.find('[name="date"]').value(appointment.start.time);
			$form.find('[name="start"]').value(appointment.start.time);
			$form.find('[name="end"]').value(appointment.end.time);
			$form.find('[name="facility"]').value(appointment.facility);
			$form.find('[name="description"]').value(appointment.description);
			$form.find('[name="content"]').value(appointment.content);
			
			magistraal.popup.open(popup);
		},

		delete: (id) => {
			magistraal.console.loading('console.loading.delete_appointment');

			magistraal.api.call({
				url: 'appointments/delete',
				data: {id: id},
				source: 'server_only'
			}).then(data => {
				magistraal.console.success('console.success.delete_appointment');
				magistraal.page.load({
					page: 'appointments/list'
				});
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
			return new Promise((resolve, reject) => {
				magistraal.api.call({
					url: 'files/download', 
					data: {location: location},
					source: 'server_only',
					callback: magistraal.files.downloadCallback,
					xhrFields: { responseType: 'arraybuffer'},
					alwaysReturn: true
				}).then(response => {
					resolve();
				}).catch(response => {
					resolve();
				})
			})
		},

		downloadCallback(arrayBuffer, loadType, request, page) {
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
		paintList: response => {
			let $html = $('<div></div>');
			
			$.each(response.data, function (i, grade) {
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
		},

		paintOverview: response => {
			const $overview         = magistraal.template.get('grade-overview');
			const $overviewTerms    = $overview.find('.grade-overview-terms');
            const $overviewSubjects = $overview.find('.grade-overview-subjects');
            const $overviewMain     = $overview.find('.grade-overview-main');

			$.each(response.data, function(courseId, course) {
				if(!course.active) {
					return true;
				}

				$.each(course.terms, function(i, term) {
					console.log(term);
				})
			})
            
            // $.each(response.data, function(courseId, course) {
			// 	if(!course.active) {
			// 		return true;
			// 	}

			// 	$.each(course.terms, function(i, term) {
			// 		const $overviewTerm = magistraal.template.get('grade-overview-term');
			// 		$overviewTerm.attr('data-id', term.id);
			// 		$overviewTerm.find('.grade-overview-term-header').text(term.name);

			// 		$overviewTerm.appendTo($overviewTerms);
			// 	})

			// 	$.each(course.columns, function(i, column) {
			// 		const $overviewTerm = $overview.find(`.grade-overview-term[data-id="${column.term.id}"]`);

			// 		// Sla deze kolom over als deze periode niet bestaat of als de kolom al bestaat
			// 		if($overviewTerm.length === 0 || $overviewTerm.find(`.grade-overview-term-column[data-number="${column.number}"][data-variant="${column.variant}"]`).length > 0) {
			// 			return true;
			// 		}

			// 		const $overviewColumn = magistraal.template.get('grade-overview-term-column');
			// 		$overviewColumn.find('.grade-overview-term-column-name').text(column.name);
			// 		$overviewColumn.attr({
			// 			'data-id':      column.id,
			// 			'data-number':  column.number,
			// 			'data-variant': column.variant
			// 		});
			// 		$overviewColumn.appendTo($overviewTerm.find('.grade-overview-term-columns'));
			// 	})

            //     $.each(course.subjects, function(i, subject) {
            //         const $overviewSubject = magistraal.template.get('grade-overview-subject');
            //         $overviewSubject.attr('data-id', subject.id).text(subject.description);
            //         $overviewSubject.appendTo($overviewSubjects);
            //     })
			// })

            // magistraal.page.setContent($overview, false);
		}
	},

	/* ============================ */
	/*           Messages           */
	/* ============================ */
	messages: {
		paintList: response => {
			// Laad de drie nieuwste berichten
			magistraal.api.call({url: 'messages/info', data: {id: String(response.data[0].id)}, source: 'prefer_cache'});
			magistraal.api.call({url: 'messages/info', data: {id: String(response.data[1].id)}, source: 'prefer_cache'});
			magistraal.api.call({url: 'messages/info', data: {id: String(response.data[2].id)}, source: 'prefer_cache'});

			let $html = $('<div></div>');

			// Ga alle berichten bij langs
			$.each(response.data, function (i, message) {
				// Maak een bericht
				let $message = magistraal.template.get('message-list-item');

				// Informatie
				message.subject = message.subject || magistraal.locale.translate('messages.subject.no_subject');
				$message.find('.message-list-item-title').text(message.subject);
				$message.find('.message-list-item-side-title').text();
				$message.find('.message-list-item-content').text(message.sender.name);

				// Attributen
				$message.attr({
					'data-id': message.id,
					'data-interesting': true,
					'data-priority': message.priority,
					'data-read': message.read,
					'data-search': message.subject + message.sender.name
				});

				// Pictogram
				const icon = message.read ? 'envelope-open' : 'envelope';
				$message.find('.message-list-item-icon').html(`<i class="fal fa-${icon}"></i>`);

				// Maak een sidebar feed
				let sidebarFeed = {
					title: message.subject,
					subtitle: message.sender.name,
					table: {
						'message.sender': message.sender.name,
						'message.sent_at': capitalizeFirst(magistraal.locale.formatDate(message.sent_at, 'ldFYHi'))
					}
				};

				// Voeg de nodige knoppen toe aan de sidebar feed
				sidebarFeed.actions = {
					delete: {
						handler: `magistraal.messages.delete('${message.id}')`, 
						icon: 'fal fa-trash'
					}
				}

				// Voeg de sidebar feed toe aan het bericht
				magistraal.sidebar.addFeed($message, sidebarFeed);

				// Voeg het bericht toe aan de inhoud
				$message.appendTo($html);
			});

			// Werk de inhoud bij
			magistraal.page.setContent($html);
		},

		view: (id, read = true) => {
			// Stuur een bericht in de console
			magistraal.console.loading('console.loading.message_content');

			// Laad het bericht
			magistraal.api.call({
				url: 'messages/info', 
				data: {id: id},
				callback: magistraal.messages.viewCallback,
				source: 'prefer_cache'
			}).then(response => {
				// Markeer het bericht als gelezen als dit nog niet het geval is
				if(!read) {
					magistraal.api.call({
						url: 'messages/read', 
						data: {id: id, read: true},
						source: 'server_only'
					}).then(response => {
						magistraal.page.load({
							page: 'messages/list',
							unobtrusive: true,
							source: 'server_only'
						});
					})
				}
			})
		},

		viewCallback: (response, loadType) => {
			let attachmentsHTML = '';
			const message       = response.data;

			// Maak de bijlage
			if(message.attachments.length > 0) {
				$.each(message.attachments, function(i, attachment) {
					attachment.icon = magistraal.mapping.icons('file_icons', attachment.mime_type);
					attachmentsHTML += `<div class="anchor" onclick="magistraal.files.download('${attachment.location}');"><i class="${attachment.icon} mr-1"></i>${attachment.name}.${attachment.type}</div>`;
				})
			}

			// Maak een sidebar feed
			let updatedFeed = {
				table: {
					'message.to': message.recipients.to.names.join(', '),
					'message.cc': message.recipients.cc.names.join(', '),
					'message.bcc': message.recipients.bcc.names.join(', '),
					'message.attachments': attachmentsHTML,
					'message.content': message.content
				},
				actions: {
					forward: {
						handler: `magistraal.messages.forward(message)`, 
						icon: 'fal fa-arrow-alt-right'
					},
					reply: {
						handler: `magistraal.messages.reply(${JSON.stringify(message)})`, 
						icon: 'fal fa-reply'
					}
				}
			};
			
			// Werk de huidige sidebar feed bij
			magistraal.sidebar.updateFeed(updatedFeed, undefined);

			// Stuur een bericht in de console
			if(loadType == 'server_final' || loadType == 'cache_final') {
				magistraal.console.success('console.success.message_content');
			}
		},

		send: (message, $form = undefined) => {
			magistraal.console.loading('console.loading.send_message');

			console.log(message);

			magistraal.api.call({
				url: 'messages/send', 
				data: message,
				source: 'server_only'
			}).then(response => {
				magistraal.console.success('console.success.send_message');
				magistraal.page.load({
					page: 'messages/list'
				});

				if($form) {
					$form.formReset();
				}
			}).catch(response => {
				magistraal.popup.open('messages_write_message');

				magistraal.console.error(`console.error.${response.responseJSON?.info}`);
			})
		},

		delete: (id) => {
			magistraal.console.loading('console.loading.delete_message');

			magistraal.api.call({
				url: 'messages/delete',
				data: {id: id},
				source: 'server_only'
			}).then(data => {
				magistraal.console.success('console.success.delete_message');
				magistraal.page.load({
					page: 'messages/list'
				});
			}).catch(response => {
				if(response.responseJSON && response.responseJSON.info) {
					magistraal.console.error(`console.error.${response.responseJSON.info}`);
					return false;
				}
			})
		},

		reply: (message) => {
			const popup = 'messages_write_message';
			const $form = magistraal.element.get('form-messages_write_message');
						
			$form.find('[name="id"]').value(message.id);
			$form.find('[name="date"]').value(message.start);
			$form.find('[name="subject"]').value(magistraal.locale.translate('message.reply.prefix')+': '+message.subject);
			
			// Restructure recipients so they can be set as tag
			let recipientTags = {to: {}, cc: {}, bcc: {}};
			$.each(message.recipients, function(recipientsGroup, recipients) {
				$.each(recipients.list, function(i, recipient) {
					recipientTags[recipientsGroup][recipient.id] = recipient.name;
				})
			})
			
			let toValue = {};
			toValue[message.sender.id] = message.sender.name;
			$form.find('[name="to"]').setTags(toValue);

			let newContent = `
				<br><br><br><hr>
				<b>${magistraal.locale.translate('messages.popup.write_message.item.from.title')}</b>: ${message.sender.name}<br>
				<b>${magistraal.locale.translate('messages.popup.write_message.item.sent_at.title')}</b>: ${magistraal.locale.formatDate(message.sent_at, 'ldFYHi')}<br>
				`+(message.recipients.to.list.length > 0 ? `<b>${magistraal.locale.translate('messages.popup.write_message.item.to.title')}</b>: ${message.recipients.to.names.join(', ')}<br>`: '')+`
				`+(message.recipients.cc.list.length > 0 ? `<b>${magistraal.locale.translate('messages.popup.write_message.item.cc.title')}</b>: ${message.recipients.cc.names.join(', ')}<br>`: '')+`
				<b>${magistraal.locale.translate('messages.popup.write_message.item.subject.title')}</b>: ${message.subject}<br><br>
			` + message.content;

			$form.find('[name="content"]').value(newContent);
			
			magistraal.popup.open(popup);
		}
	},

	/* ============================ */
	/*           Settings           */
	/* ============================ */
	settings: {
		paintList: response => {
			// Laad de huidige instellingen
			let currentSettings = magistraalPersistentStorage.get('settings').value || [];

			let $html = $('<div></div>');

			// Ga alle mogelijke instellingen bij langs
			$.each(response.data.items, function (itemNamespace, item) {
				let $item = $('<div></div>');

				// Als item een categorie is
				if(isSet(item.items)) {
					// Maak een item
					$item = magistraal.template.get('setting-category');

					// Informatie
					$item.find('.setting-category-title').text(magistraal.locale.translate(`settings.category.${response.data.category}.${itemNamespace}.title`));
					$item.find('.setting-category-icon').html(`<i class="${item?.icon || 'cog'}"></i>`); 
					
					// Beschrijving (bestaat uit de namen van de sub-items in deze categorie)
					let $content = $('<div></div>');
					$.each(item.items, function (childItemNamespace, childItem) {
						if(isSet(childItem.items)) {
							// Sub-item is een categorie
							$content.append(`<span>${magistraal.locale.translate(`settings.category.${itemNamespace}.${childItemNamespace}.title`)}</span><span class="bullet"></span>`);
						} else {
							// Sub-item is een instelling
							$content.append(`<span>${magistraal.locale.translate(`settings.setting.${itemNamespace}.${childItemNamespace}.title`)}</span><span class="bullet"></span>`);
						}
					});

					// Verwijder de laatste bullet uit de inhoud
					$content.find('.bullet:last-child').remove();

					$item.find('.setting-category-content').html($content.html());
					$item.attr('onclick', `magistraal.page.load({page: 'settings/list', data: {'category': '${itemNamespace}'}, showBack: true});`);
				
				// Als item een instelling is
				} else {
					$item       = magistraal.template.get('setting-list-item');
					let $input  = $('<input class="form-control input-search">')
					let setting = `${response.data.category}.${itemNamespace}`;
					$input.appendTo($item.find('.list-item-content'));

					if(isSet(currentSettings[setting])) {
						item.value = currentSettings[setting];
					}
					
					// Aanpassingen per instelling
					switch(setting) {
						case 'appearance.theme':
							// Remap dark_auto en light_auto naar auto
							if(isSet(item.value) && (item.value == 'dark_auto' || item.value == 'light_auto')) {
								item.value = 'auto';
							}
							break;

						case 'system.version':
							item.value = magistraalPersistentStorage.get('version').value || '-';
							break;

						case 'system.user_uuid':
							item.value = magistraalPersistentStorage.get('user_uuid').value || '-';
							break;

						case 'system.user_agent':
							item.value = navigator.userAgent || '-';
							break;
					}
					
					// Voeg een zoekinput toe
					let inputObj           = new magistraal.inputs.searchInput($input);
					const value            = item?.value || item?.default || null;
					const valueTranslation = magistraal.locale.translate(`settings.setting.${setting}.value.${value}.title`, magistraal.locale.translate('generic.invalid'));
					let results            = [];

					// Als de waarde van deze instelling kan worden gewijzigd
					if(!isSet(item.enabled) || item.enabled == 'true') {
						// Ga alle mogelijke waarden van deze instelling bij langs
						$.each(item.values, function(itemValue, itemInfo) {
							const itemTitle = magistraal.locale.translate(`settings.setting.${setting}.value.${itemInfo?.title}.title`);
							
							results.push({
								title: itemTitle,
								value: itemValue,
								icon: itemInfo?.icon,
								description: itemInfo?.description || itemTitle
							});
						})

						// Voeg de waarden toe als zoekresultaten
						inputObj.results.set(results);

						// Stel de waarde van de zoekinvoer in op de huidige waarde van de instelling
						$input.value(value);
						$input.val(valueTranslation);
						inputObj.results.select(value);
					
					// Als de waarde van deze instelling niet kan worden gewijzigd
					} else {
						// Stel de inhoud van het item in op de huidige waarde
						$item.removeClass('list-item-with-input');
						$item.find('.list-item-content').text(value);
					}

					// Attributen
					$item.attr({
						'data-setting': `${response.data.category}.${itemNamespace}`,
						'data-reload': item?.options?.do_reload || false
					})

					// Pictogram en titel
					$item.find('.list-item-icon').html(`<i class="${item?.icon || 'cog'}"></i>`); 
					$item.find('.list-item-title').text(magistraal.locale.translate(`settings.setting.${response.data.category}.${itemNamespace}.title`));
				}

				// Voeg item toe aan de inhoud
				$item.appendTo($html);
			});

			// Werk de inhoud bij
			magistraal.page.setContent($html);
		},

		updateClient: (settings, updateOnServer = false) => {
			if(!isSet(settings)) {
				settings = {};
			}

			let newSettings = {};
			Object.assign(newSettings, settings);

			if(isSet(settings['appearance.theme']) && settings['appearance.theme'].includes('auto')){
				if(window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
					newSettings['appearance.theme'] = 'dark_auto';
				} else {
					newSettings['appearance.theme'] = 'light_auto';
				}
			}
			magistraalPersistentStorage.set('settings', settings);

			if(Object.entries(settings).toString() != Object.entries(newSettings).toString() && updateOnServer) {
				magistraal.settings.set_all(newSettings);
			}

			let settingsString = '';
			$.each(newSettings, function(key, value) {
				settingsString += `${key}=${value},`
			})

			$('body').attr('data-settings', trim(settingsString, ','));
		},

		get_all: () => {
			return new Promise((resolve, reject) => {
				magistraal.api.call({
					url: 'user/settings/get_all',
					alwaysReturn: true
				}).then(response => {
					resolve(response.data);
				}).catch(response => {
					magistraal.console.error();
					reject(response.data);
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
				}).then(response => {
					magistraal.settings.updateClient(response.data);

					magistraal.console.success('console.success.save');

					resolve();
				}).catch(err => {
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
				}).then(response => {
					magistraal.settings.updateClient(response.data);

					magistraal.console.success('console.success.save');

					resolve();
				}).catch(response => {
					magistraal.console.error();
					reject();
				})
			})
		}
	},

	/* ============================ */
	/*            Sources           */
	/* ============================ */
	sources: {
		paintList: response => {
			$html = $('<div></div>');

			$.each(response.data, function(i, source) {
				const $source     = magistraal.template.get('source-list-item');
				const icon        = magistraal.mapping.icons('file_icons', source.type == 'file' ? source.content_type : source.type);
				const description = magistraal.mapping.translations('file_types', source.type == 'file' ? source.content_type : source.type);
				
				$source.find('.list-item-title').text(source.name);
				$source.find('.list-item-content').text(description);
				$source.find('.list-item-icon').html(`<i class="fal fa-${icon}"></i>`);
				$source.attr('data-search', source.name);

				if(source.type == 'folder') {
					$source.attr('onclick', `magistraal.page.load({page: 'sources/list', data: {parent_id: '${source.id}'}, showBack: true});`);
				} else if(source.type == 'file') {
					$source.attr('onclick', 'magistraal.sidebar.selectFeed($(this))');

					let sidebarFeed = {
						title: source.name,
						subtitle: description,
						actions: {
							download: {
								handler: `$(this).attr('disabled', 'disabled'); magistraal.files.download('${source.location}').then(response => { $(this).removeAttr('disabled'); })`, 
								icon: 'fal fa-arrow-to-bottom'
							}
						}
					};

					magistraal.sidebar.addFeed($source, sidebarFeed);
				}

				$source.appendTo($html);
			})

			magistraal.page.setContent($html);
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

		clear: () => {
			$('.console-message').remove();
			return;
		},

		send: (message, type = 'success', duration = 1500) => {
			message = magistraal.locale.translate(message, magistraal.locale.translate(`console.${type}.generic`, message));
			let messageId = Date.now();
			// console.log(`${type}: ${message}`);
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
		try {
			magistraalStorage.set('api', '/magistraal/api/');

			if(!isSet(parameters.version)) {
				return false;
			}

			// If current version is not equal to new one, soft-clear cache
			if(magistraalPersistentStorage.get('version').value != parameters?.version) {
				console.log(`New version (${parameters?.version}) was found!`);
				magistraalPersistentStorage.clear(true);
			}

			const settings = magistraalPersistentStorage.get('settings').value;

			const language = (isSet(settings) && isSet(settings['appearance.language']) ? settings['appearance.language'] : 'nl_NL');

			magistraalStorage.set('version', parameters?.version);
			magistraalPersistentStorage.set('version', parameters?.version);

			return new Promise((resolve, reject) => {
				magistraal.locale.load(language).then(() => {
					$(document).trigger('magistraal.ready');
					resolve();
				}).catch(() => {});

				if(parameters?.doPreCache === true) {
					// Laad absenties, afspraken, berichten etc. voor offlinegebruik
					// magistraal.api.call({url: 'absences/list', source: 'prefer_cache'});
					// magistraal.api.call({url: 'appointments/list', source: 'prefer_cache'});
					// magistraal.api.call({url: 'grades/list', source: 'prefer_cache'});
					// magistraal.api.call({url: 'messages/list', source: 'prefer_cache'});
					// magistraal.api.call({url: 'settings/list', source: 'prefer_cache'});
					// magistraal.api.call({url: 'settings/list', data: {category: 'appearance'}, source: 'prefer_cache'});
					// magistraal.api.call({url: 'settings/list', data: {category: 'system'}, source: 'prefer_cache'});
				}
			});
		} catch(err) {
			console.error(err);
		}
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

		loadCallback: (response, source) => {
			magistraalStorage.set('translations', response.data);
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
			let translations = magistraalStorage.get('translations').value;

			if(!isSet(translations) || !isSet(translations[key])) {
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
			if(magistraalStorage.get('nav_active').value == 'true') {
				magistraal.nav.close();
			} else {
				magistraal.nav.open();
			}
		}
	},

	page: {
		load: (parameters) => {
			if(!isSet(parameters.cachable))    { parameters.cachable = null; }
			if(!isSet(parameters.data))        { parameters.data = {}; }
			if(!isSet(parameters.page))        { return false; }
			if(!isSet(parameters.source))      { parameters.source = null; }
			if(!isSet(parameters.showBack))    { parameters.showBack = false; }
			if(!isSet(parameters.unobtrusive)) { parameters.unobtrusive = false; }

			if(parameters.page.includes('?')) {
				// Query string is embedded in url, extract it
				[parameters.page, parameters.data] = parameters.page.split('?');
				parameters.data = Object.fromEntries(new URLSearchParams(parameters.data));
			}

			if(isSet(parameters.data.activity)) { delete parameters.data.activity; }

			// Aanpassingen per pagina
			switch(parameters.page) {
				case 'login':
					window.location.href = '../login/';
					return;
				
				case 'main':
					window.location.href = '../main';
					return;
				
				case 'settings/list':
					parameters.source = 'prefer_cache';
					break;
			}
		
			// Sluit popup(s)
			magistraal.popup.close();
				
			// Wijzig de URL
			if(parameters.showBack) {
				magistraal.page.pushState('backBtn', null, null);
				magistraal.page.modifyLocation(parameters.page, parameters.data, 'replace');
			} else {
				magistraal.page.modifyLocation(parameters.page, parameters.data, 'push');
			}

			if(!parameters.unobtrusive) {
				// Maak de sidebar leeg en sluit deze
				magistraal.sidebar.clearFeed();
				magistraal.sidebar.close();

				// Maak de zoekbalk leeg
				magistraal.element.get('page-search').val('');

				// Verberg het "Geen resultaten gevonden!" bericht
				magistraal.element.get('page-search-no-matches').removeClass('show');

				// Scroll pagina naar boven
				magistraal.element.get('main').scrollTop(0);
			}

			// Wijzig de geselecteerde knop in de navigatiebalk
			$('.nav-item').removeClass('active');
			magistraal.element.get(`nav-item-${parameters.page}`).addClass('active');
			
			// Laad de pagina
			magistraal.page.get(parameters);
		},

		get: (parameters) => {
			// Lijst van callbacks
			const callbacks = {
				'absences/list':     magistraal.absences.paintList,
				'appointments/list': magistraal.appointments.paintList,
				'grades/list':       magistraal.grades.paintList,
				'grades/overview':   magistraal.grades.paintOverview,
				'messages/list':     magistraal.messages.paintList,
				'logout':            magistraal.logout.logout,
				'settings/list':     magistraal.settings.paintList,
				'sources/list':      magistraal.sources.paintList
			};

			return new Promise((resolve, reject) => {
				// Juiste callback kiezen
				if(!isSet(callbacks[parameters.page])) {
					reject();
					return false;
				}
				const callback = callbacks[parameters.page];

				magistraal.console.loading('console.loading.refresh');

				magistraal.api.call({
					url: parameters.page, 
					data: parameters.data, 
					source: parameters.source,
					cachable: parameters.cachable, 
					callback: function(response, loadType, request, page) {
						callback(response, loadType, request, page);
						magistraal.page.getCallback(response, loadType, request, page);
					}, 
				}).then(response => {
					resolve(response);
				}).catch(() => {});
			});
		},

		getCallback: (response, loadType, request, page) => {
			// Selecteer eerste item in lijst
			const $li_first = magistraal.element.get('main').find('.list-item[data-interesting="true"]').first();
			magistraal.sidebar.selectFeed($li_first, false);

			// Werk paginaknoppen bij
			const $pageButtonsTemplate = magistraal.template.get(`page-buttons-${page}`);
			const $pageButtonsContainer = magistraal.element.get('page-buttons-container');
			$pageButtonsContainer.html($pageButtonsTemplate.length >= 1 ? $pageButtonsTemplate.html() : '');

			// Werk de paginatitel bij
			const $pageTitle = magistraal.element.get('page-title');
			const pageTitle  = magistraal.locale.translate(`generic.page.${page}.title`);
			$pageTitle.text(pageTitle);
			document.title = `${pageTitle} | Magistraal`;

			if(loadType == 'server_final') {
				magistraal.console.success('console.success.refresh');
			} else if(loadType == 'cache_final') {
				magistraal.console.clear();
			}

			// Filter de items als de gebruiker al een zoekterm heeft ingevuld
			const $pageSearch = magistraal.element.get('page-search');
			if($pageSearch.value().length > 0) {
				$pageSearch.trigger('input');
			}
		},

		current: (ignoreQuery = false, ignoreActivity = true) => {
			const page = trim(window.location.hash.substring(2), '/');
			
			if(ignoreQuery) {
				return page.split('?')[0];
			}

			return page;
		},

		previous: () => {
			return magistraalStorage.get('previousPage').value || '';
		},
		
		setContent: ($el, useChildren = true) => {
			$('main').empty().append(useChildren ? $el.children() : $el);
		},

		pushState: (data, unused, string) => {
			window.history.pushState(data, unused, string);
			$('body').attr('data-history', 'true');
		},

		back: (goBack = true) => {
			if(goBack) {
				window.history.back();
			}

			if(magistraal.page.previous().includes(magistraal.page.current(true)) && magistraal.page.current().includes('?')) {
				return true;
			}

			$('body').removeAttr('data-history');
		},

		modifyLocation: (page, data = {}, method = 'push') => {
			$.each(data, function(key, value) {
				if(!isSet(value)) {
					delete data[key];
				}
			})

			const currentHash = magistraal.page.current(false, false);
			const query       = new URLSearchParams(data).toString();
			const seperator   = query.length > 0 
									? page.includes('?')
										? '&'
										: '?'
									: ''; 
			const newHash     = page + seperator + query;

			// Voor popstate event 
			magistraalStorage.set('previousPage', magistraal.page.current());

			$('body').attr('data-page', page);

			// Verander url
			if(currentHash != newHash) {
				if(method == 'push') {
					window.history.pushState(newHash, null, '#/' + newHash);
				} else if(method == 'replace') {
					window.history.replaceState(newHash, null, '#/' + newHash);
				}
			}
		}
	},

	login: {
		login: (credentials) => {
			magistraal.console.loading('console.loading.login');

			magistraal.api.call({
				url: 'login', 
				data: credentials,
				source: 'server_only',
			}).then(response => {
				if(isSet(response.data.user_uuid)) {
					magistraalPersistentStorage.set('user_uuid', response.data.user_uuid);
				}

				window.location.href = '../main/';
			}).catch(response => {
				magistraal.console.error(`console.error.${response.responseJSON.info}`);
			});
		}
	},

	inputs: {
		dialog: class {
			constructor(parameters = {}) {
				if(!isSet(parameters.title))         { parameters.title = ''; }
				if(!isSet(parameters.description))   { parameters.description = ''; }
				if(!isSet(parameters.defaultAnswer)) { parameters.defaultAnswer = 'no'; }

				this.parameters = parameters;

				return this;
			}

			open() {
				return new Promise((resolve, reject) => {
					if($('.dialog:not([data-magistraal-template]').length > 0) {
						reject();
						return;
					}

					this.$dialog = magistraal.template.get('dialog');

					this.$dialog.find('.dialog-title').text(this.parameters.title);
					this.$dialog.find('.dialog-description').html(this.parameters.description);

					window.history.pushState('preventDialogClose', null, null);

					this.$dialog.appendTo('body');
					
					setTimeout(() => {
						this.$dialog.addClass('show');
					}, 1);

					this.$dialog.find(`[data-dialog-action="${this.parameters.defaultAnswer}"]`).attr('data-selected', true);
					magistraal.element.get('dialog-backdrop').addClass('show');

					const dialogClass = this;
					this.$dialog.find('[data-dialog-action]').on('click', function() {
						const $btn = $(this);
						if($btn.attr('data-dialog-action') == 'yes') {
							resolve();
						} else {
							reject();
						}

						dialogClass.close();
					})
				})
			}

			close() {
				if(!isSet(this.$dialog)) {
					return false;
				}

				window.history.go(-1);
				magistraal.element.get('dialog-backdrop').removeClass('show');

				this.$dialog.removeClass('show');
				setTimeout(() => {
					this.$dialog.remove();
				}, 250);
			}
		},

		tagsInput: class {
			constructor($input) {
				this.$input = $input;
				this.setup();
			}

			setup() {
				if(this.$input.closest('.input-wrapper').length == 0) {
					this.$input.wrap('<div class="input-wrapper"></div>');
				}

				this.$input.data('tagsInput', this);

				this.$wrapper = this.$input.closest('.input-wrapper');
				this.$wrapper.addClass('input-tags-wrapper');
				this.$tags = $('<ul class="input-tags-list"></ul>');
				this.$tags.appendTo(this.$wrapper); // Create ghost input

				if(this.$wrapper.hasClass('input-search-wrapper')) {
					this.$inputGhost = $('<input type="text" class="form-control input-search input-ghost">');
					this.$inputGhost.appendTo(this.$wrapper); // Move search input to tags list

					this.$tags.append(this.$input.removeClass('input-search').detach());
				}

				this.$wrapper.on('click', e => {
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

				this.$input.data('searchInput', this);

				this.$wrapper = this.$input.closest('.input-wrapper');
				this.$wrapper.addClass('input-search-wrapper');
				this.$results = $('<ul class="input-search-results"></ul>');
				this.$results.appendTo(this.$wrapper);
				if(!this.$input.attr('placeholder')) {
					this.$input.attr('placeholder', magistraal.locale.translate('generic.action.search'));
				}

				this.$wrapper.on('click', e => {
					if($(e.target).closest('.input-search-results').length === 0) {
						this.eventFocus(e);
					}
				});

				$(document).on('click', e => {
					if(!$(e.target).closest('.input-wrapper').is(this.$wrapper)) {
						this.eventFocusOut(e);
					}
				});
				
				if(isSet(this.$input.attr('data-magistraal-search-api'))) {
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
				this.$wrapper.removeClass('active');
			}

			eventInput(e) {
				if(isSet(this.$input.attr('data-magistraal-search-api'))) {
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
				this.results.select(value);

				if(this.$wrapper.hasClass('input-tags-wrapper')) {
					let $inputTags = this.$wrapper.find('.input-tags');
					$inputTags.trigger({
						type: 'magistraal.change',
						addTag: {
							value: value,
							text: text
						}
					});
					// this.$input.focus();
					// setTimeout(() => {
					// 	this.$wrapper.addClass('active');
					// 	this.$wrapper.find('.input-ghost')?.addClass('focus');
					// }, 50);
				} else {
					this.$input.val(text).attr('data-value', value);
					this.$input.trigger('magistraal.change');
				}
			
				this.$wrapper.removeClass('active');
			}
			
			results = {
				set: (results) => {
					let html = '';

					$.each(results, function (i, result) {
						if(!isSet(result.icon) || !isSet(result.description)) {
							html += `<li class="input-search-result" value="${result?.value}"><span class="input-search-result-title">${result?.title}</span></li>`;
						} else {
							html += `<li class="input-search-result input-search-result-rich" value="${result?.value}"><i class="input-search-result-icon ${result?.icon}"></i><span class="input-search-result-title">${result?.title}</span><span class="input-search-result-description">${result?.description}</span></li>`;
						}
					});
					
					this.$results.html(html);
				},

				select: (value = undefined) => {
					this.$results.find('.input-search-result').removeAttr('data-selected');
					this.$results.find(`.input-search-result[value="${value}"]`).attr('data-selected', true);
				}
			}
		},

		search: {
			remap_api_response: (api, response) => {
				let result = [];

				switch (api) {
					case 'people':
						$.each(response.data, function (i, person) {
							result.push({
								'icon': person.type == 'student' ? 'fal fa-book-open' : 'fal fa-briefcase',
								'title': person.infix == '' ? `${person.first_name} ${person.last_name}` : `${person.first_name} ${person.infix} ${person.last_name}`,
								'description': person.course || person.abbr || magistraal.locale.translate(`people.type.${person.type}`),
								'value': person.id
							});
						});
						break;

					case 'tenants':
						$.each(response.data, function(i, tenant) {
							result.push({
								'icon': 'fal fa-school',
								'title': tenant.name,
								'description': tenant.name,
								'value': tenant.id
							});
						})
						break;
				}

				return result;
			}
		}
	},
	logout: {
		logout: () => {
			magistraal.api.call({
				url: 'logout',
				source: 'server_only'
			}).finally(() => {
				magistraalPersistentStorage.clear();
				magistraal.page.load({
					page: 'login'
				});
			});
		}
	},
	sidebar: {
		addFeed: ($elem = undefined, feed) => {
			feed.title    = feed?.title || '';
			feed.subtitle = feed?.subtitle || '';
			feed.table    = feed?.table || {};
			feed.actions  = feed?.actions || {};
			$elem = $elem || magistraal.element.get('sidebar');
			$elem.attr('data-sidebar-feed', encodeURI(JSON.stringify(feed)));
			return true;
		},

		clearFeed: () => {
			return magistraal.sidebar.selectFeed(undefined);
		},

		getFeed: ($elem = undefined) => {
			$elem = $elem || magistraal.element.get('sidebar');
			return JSON.parse(decodeURI($elem.attr('data-sidebar-feed') || '{}'));
		},

		selectFeed: ($el, openSidebar = true) => {
			if(!isSet($el)) {
				return magistraal.sidebar.setFeed(undefined, false);
			}

			$(`[class="${$el.attr('class')}"]`).removeAttr('data-selected');
			$el.attr('data-selected', true);
			magistraal.sidebar.setFeed(magistraal.sidebar.getFeed($el), openSidebar);
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
				let actionColor = magistraal.mapping.colors('sidebar_action', actionType);
				$action.addClass(`btn-${actionColor}`);

				$action.html(`
					<i class="btn-icon ${action?.icon}"></i>
					<span class="btn-text">${magistraal.locale.translate(`generic.action.${actionType}`)}</span>
				`);
				
				$action.attr({'data-action': actionType, 'onclick': action?.handler});
				$action.appendTo($sidebarActions);
			})

			if(openSidebar) {
				magistraal.sidebar.open();
			}
		},

		updateFeed: (updatedFeed, insertTableAfterKey = undefined) => {
			let newFeed                = {title: '', subtitle: '', table: {}, actions: {}};
			let currentFeed            = magistraal.sidebar.getFeed();
			let currentFeedTableLength = Object.keys(currentFeed.table).length;
			
			// Overwrite title and subtitle
			newFeed.title = updatedFeed?.title || currentFeed.title; 
			newFeed.subtitle = updatedFeed?.subtitle || currentFeed.subtitle; 

			// Merge table
			let i = 1;
			$.each(currentFeed.table, function(currentTableKey, currentTableValue) {
				newFeed.table[currentTableKey] = currentTableValue;

			    if(currentTableKey == insertTableAfterKey || i == currentFeedTableLength) {
					$.each(updatedFeed.table, function(updateTableKey, updateTableValue) {
						newFeed.table[updateTableKey] = updateTableValue;
					})
				}

				i++;
			})

			// Merge actions
			Object.assign(newFeed.actions, currentFeed.actions, updatedFeed?.actions);

			magistraal.sidebar.setFeed(newFeed, false);
		},

		currentFeed() {
			let $sidebar = magistraal.element.get('sidebar');
			return JSON.parse(decodeURI($sidebar.attr('data-sidebar-feed') || '{}'));
		},

		open: () => {
			if(magistraalStorage.get('sidebar_active').value === true) {
				return false;
			}
			
			if(window.innerWidth < 768) {
				magistraal.page.pushState('preventSidebarClose', null, null);
			}

			$('body').attr('data-sidebar-active', true);
			magistraalStorage.set('sidebar_active', true);
		},

		close: (goBack = false) => {
			if(magistraalStorage.get('sidebar_active').value === false) {
				return false;
			}

			if(goBack) {
				window.history.go(-1);
			}

			$('body').attr('data-sidebar-active', false);
			magistraalStorage.set('sidebar_active', false);
		},

		toggle: () => {
			if(magistraalStorage.get('sidebar_active').value == 'true') {
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
			
			window.history.pushState('preventPopupClose', null, null);

			magistraal.element.get('popup-backdrop').addClass('show');
			magistraal.popup.enable(selector);
			$popup.addClass('show');
		},

		close: (selector  = undefined, goBack = true, clearForm = false) => {
			if(!isSet(selector)) {
				selector = $('[data-magistraal-popup].show').last().attr('data-magistraal-popup');
			}

			let $popup = magistraal.popup.get(selector);

			if($popup.length === 0) {
				return false;
			}

			magistraal.element.get('popup-backdrop').removeClass('show');
			magistraal.popup.disable(selector);
			$popup.removeClass('show');

			if(clearForm) {
				const $form = $popup.find('form').first();
				if($form.length) {
					$form.formReset();
				}
			}

			if(goBack) {
				window.history.back();
			}

			// return new Promise((resolve, reject) => {
			// 	if($popup.length === 0) {
			// 		resolve();
			// 	}

			// 	// let $form = $popup.find('form').first();
			// 	// if($form.length && $form.formHasChanges() && showDialog) {
			// 	// 	// Toon een dialog aan de gebruiker
			// 	// 	new magistraal.inputs.dialog({
			// 	// 		title: magistraal.locale.translate(`generic.dialog.popup_close.${selector}.title`, magistraal.locale.translate('generic.dialog.popup_close.title')),
			// 	// 		description: magistraal.locale.translate(`generic.dialog.popup_close.${selector}.description`, magistraal.locale.translate('generic.dialog.popup_close.description')),
			// 	// 		defaultAnswer: 'yes'
			// 	// 	}).then(() => {
			// 	// 		resolve();
			// 	// 		$form.formReset();
			// 	// 		return magistraal.popup.closeCallback($popup, selector);
			// 	// 	}).catch(() => {
			// 	// 		reject();
			// 	// 	})
			// 	// } else {
			// 	// 	resolve();
			// 	// 	$form.formReset();
			// 	// 	return magistraal.popup.closeCallback($popup, selector);
			// 	// }
			// })
		},

		// closeCallback: ($popup, selector) => {
		// 	magistraal.element.get('popup-backdrop').removeClass('show');
		// 	magistraal.popup.disable(selector);
		// 	$popup.removeClass('show');

		// 	// Verwijder 'preventPopupClose' state
		// 	window.history.back();
		// },

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
			setCookie('magistraal-authorization', undefined, -1);
			return true;
		}
	},
	
	mapping: {
		icons: (category = '', selector = '') => {
			switch(category) {
				case 'file_icons':
					if(selector == 'application/msword' || selector == 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') {
						return 'fal fa-file-word';
					} else if(selector == 'application/vnd.ms-powerpoint' || selector == 'application/vnd.openxmlformats-officedocument.presentationml.presentation') {
						return 'fal fa-file-powerpoint';
					} else if(selector == 'application/vnd.ms-excel' || selector == 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet') {
						return 'fal fa-file-excel';
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
					} else if(selector == 'folder') {
						return 'fal fa-folder';
					} else {
						return 'fal fa-file';
					}
			}

			return 'fal fa-question';
		},

		translations: (category = '', selector = '') => {
			switch(category) {
				case 'file_types':
					if(selector == 'application/msword' || selector == 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') {
						return magistraal.locale.translate('generic.file_type.word');
					} else if(selector == 'application/vnd.ms-powerpoint' || selector == 'application/vnd.openxmlformats-officedocument.presentationml.presentation') {
						return magistraal.locale.translate('generic.file_type.powerpoint');
					} else if(selector == 'application/vnd.ms-excel' || selector == 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet') {
						return magistraal.locale.translate('generic.file_type.excel');
					} else if(selector == 'application/pdf') {
						return magistraal.locale.translate('generic.file_type.pdf');
					} else if(selector.includes('image/')) {
						return magistraal.locale.translate('generic.file_type.image');
					} else if(selector.includes('video/')) {
						return magistraal.locale.translate('generic.file_type.video');
					} else if(selector.includes('audio/')) {
						return magistraal.locale.translate('generic.file_type.audio');
					} else if(selector.includes('text/')) {
						return magistraal.locale.translate('generic.file_type.text');
					} else if(selector == 'application/zip' || selector == 'application/x-zip-compressed' || selector == 'application/x-7z-compressed' || selector == 'application/vnd.rar' || selector == 'application/x-bzip' || selector == 'application/x-bzip2') {
						return magistraal.locale.translate('generic.file_type.archive');
					} else if(selector == 'folder') {
						return magistraal.locale.translate('generic.file_type.folder');
					} else {
						return magistraal.locale.translate('generic.file_type.file');
					}
			}

			return '';
		},

		colors: (category = '', selector = '') => {
			switch(category) {
				case 'sidebar_action': 
					switch(selector) {
						case 'delete':
							return 'danger';
						
						case 'join_meeting': 
							return 'teams';

						case 'finish':
							return 'success';

						default:
							return 'secondary';
					}
			}
		}
	}
};