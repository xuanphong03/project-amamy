<div class="remodal remodal-bulk-edit remodal-draggable" data-remodal-id="modal-bulk-edit"
	data-remodal-options="closeOnOutsideClick: false, hashTracking: false" x-data="bulkEdit">

	<div class="modal-content">
		<h3><?php _e( 'Bulk Edit', 'vg_sheet_editor' ); ?></h3>
		<p class="formula-tool-description" x-show="!executionInProgress">
			<?php _e( 'Using this tool you can update thousands of rows at once', 'vg_sheet_editor' ); ?>
			<?php if ( is_admin() && VGSE()->helpers->user_can_manage_options() ) { ?>
			<a class="help-button" href="<?php echo esc_url( $tutorials_url ); ?>"
				target="_blank"><?php _e( 'Need help? Check our tutorials', 'vg_sheet_editor' ); ?></a>
			<?php } ?>
		</p>
		<form action="" method="post" x-show="!executionInProgress" @submit.prevent="submit">
			<ul class="unstyled-list">
				<li>
					<label
						for="bulk-edit-select-rows"><?php _e( 'Select the rows that you want to update.', 'vg_sheet_editor' ); ?></label>
					<select x-model="rowsSelectionType" name="bulk-edit-select-rows" class="wpse-select-rows-options"
						required>
						<option value="">--</option>
						<option value="current_search">
							<?php _e( 'Edit all the rows from my current search (including non-visible rows).', 'vg_sheet_editor' ); ?>
						</option>
						<option value="new_search">
							<?php _e( 'I want to search rows to update and edit all the search results', 'vg_sheet_editor' ); ?>
						</option>
						<option value="selected">
							<?php _e( 'Edit the rows that I selected manually in the spreadsheet.', 'vg_sheet_editor' ); ?>
						</option>
						<option x-show="hasExecutedBulkEditPreviously" value="previously_selected">
							<?php _e( 'Edit the same rows that I manually selected previously', 'vg_sheet_editor' ); ?>
						</option>
					</select>
					<button x-show="rowsSelectionType.new_search" class="wpse-formula-post-query button"
						@click.prevent="goSearch"><?php _e( 'Make another search', 'vg_sheet_editor' ); ?></button>

				</li>
				<li x-show="showColumnSelector">

					<label><?php _e( 'What field do you want to edit?', 'vg_sheet_editor' ); ?></label>
					<select x-model="columns" name="columns[]" required
						data-placeholder="<?php _e( 'Select column...', 'vg_sheet_editor' ); ?>" class="select2 multiple-column-selector"
						multiple x-html="columnOptionsHtml" x-ref="columnsSelector"></select>
					<?php if ( is_admin() && VGSE()->helpers->user_can_manage_options() && empty( VGSE()->options['enable_simple_mode'] ) ) { ?>
					<br /><span x-show="!columns.length"
						class="formula-tool-missing-column-tip"><small><?php _e( 'A column is missing? <a href="#" data-remodal-target="modal-columns-visibility">Enable it</a>', 'vg_sheet_editor' ); ?></small></span>
					<?php } ?>
				</li>
				<template x-if="columns.length">
					<li x-show="showActions">
						<div class="bulk-edit-types">
							<label for="bulk-edit-type"><?php _e( 'Select type of edit', 'vg_sheet_editor' ); ?></label>
							<select name="action_name" x-model="bulkEditType">
								<option value=""><?php _e( '- -', 'vg_sheet_editor' ); ?></option>
								<template x-for="(actionLabel, actionKey) in bulkEditTypes" :key="actionKey">
									<option :selected="bulkEditType === actionKey" :value="actionKey" x-text="actionLabel">
									</option>
								</template>
							</select>
						</div>
						<div class="builder-fields">
							<p class="action-description" x-html="actionDescription" x-show="actionDescription"></p>
							<div class="action-fields">
								<template x-for="(field, key) in builderFields" :key="key">
									<div class="vg-field">
										<template x-if="field.label && field.tag !== 'a' && field.tag !== 'span'">
											<label>
												<span x-html="field.label"></span>
												<template x-if="field.tooltip">
													<a href="#" data-wpse-tooltip="right" :aria-label="field.tooltip">( ?
														)</a>
												</template>
											</label>
										</template>
										<template x-if="field.tag === 'select'">
											<select
												x-init="if(field.html_attrs.class && field.html_attrs.class.indexOf('select2') >-1) {window.vgseInitSelect2($el);fixSelect2();}"
												x-bind="getSelectHtmlAttributes(field)" :data-extra_ajax_parameters="field.html_attrs['data-extra_ajax_parameters'] || ''" :data-builder-field-index="key" x-model="builderFieldsData[key]"
												name="formula_data[]" x-html="field.options">
											</select>
										</template>
										<template x-if="field.tag === 'input'">
											<input x-model="builderFieldsData[key]" name="formula_data[]"
												x-bind="field.html_attrs">
										</template>
										<template x-if="field.tag === 'textarea'">
											<div class="textarea-wrapper">
												<textarea x-model="builderFieldsData[key]" name="formula_data[]"
													x-bind="field.html_attrs"
													x-init="if(field.html_attrs.class && field.html_attrs.class.indexOf('formula-field-tinymce') >-1) {initTinyMce(key)}"></textarea>
											</div>
										</template>
										<template x-if="field.tag === 'a'">
											<span>
												<a @click.prevent="openMediaLibrary(key, field.html_attrs['data-multiple'])"
													x-bind="field.html_attrs" x-text="field.label"></a>
												<input type="hidden" x-model="builderFieldsData[key]"
													name="formula_data[]" />
												<template x-if="builderFieldsData[key]">
													<div class="selected-files">
														<template x-for="url in builderFieldsData[key].split(',')">
															<div class="image-preview">
																<button type="button" class="remove-file"
																	@click.prevent="removeFile(key, url)">x</button>
																<img :src="url" width="80" height="80" />
															</div>
														</template>
													</div>
												</template>
											</span>
										</template>
										<template x-if="field.description">
											<p x-html="field.description"></p>
										</template>
									</div>
								</template>
							</div>
						</div>
						<div class="preview-wrapper" x-show="isPreviewAllowed">
							<button x-show="!isGeneratingPreview" @click.prevent="generatePreview" type="button"
								class="generate-preview button"><?php _e( 'Show preview', 'vg_sheet_editor' ); ?></button>
								<template x-if="previewResult.message">
									<p x-text="previewResult.message"></p>                                
								</template>
							<template x-if="previewResult.rowId">
								<div class="preview-result">
									<p><strong><?php _e( 'Sample row ID', 'vg_sheet_editor' ); ?>:</strong> <span
											x-text="previewResult.rowId"></span></p>
									<div x-show="previewResult.before"><strong><?php _e( 'Old value', 'vg_sheet_editor' ); ?>:</strong></div>
									<div x-text="previewResult.before"></div>
									<div><strong><?php _e( 'New value', 'vg_sheet_editor' ); ?>:</strong></div>
									<template
										x-if="previewResult.after.indexOf(window.location.origin) === 0 && vgse_editor_settings.final_spreadsheet_columns_settings[columns[0]].type.indexOf('boton_gallery') === 0">
										<div><img :src="previewResult.after" alt="Image preview"></div>
									</template>
									<template
										x-if="!(previewResult.after.indexOf(window.location.origin) === 0 && vgse_editor_settings.final_spreadsheet_columns_settings[columns[0]].type.indexOf('boton_gallery') === 0)">
										<div x-text="previewResult.after"></div>
									</template>
								</div>
							</template>
						</div>
					</li>
				</template>
				<?php if ( empty( VGSE()->options['enable_simple_mode'] ) ) { ?>
					<template x-if="canShowSlowExecutionField()">
						<li class="use-slower-execution-field">
							<label><input x-model="useSlowerExecution" type="checkbox" value="yes"
									name="use_slower_execution"><?php _e( 'Use slower execution method?', 'vg_sheet_editor' ); ?>
								<a href="#" data-wpse-tooltip="right"
									aria-label="<?php _e( 'The default way uses a faster execution method, but it might not work in all the cases. Use this option when the default way doesn\'t work or doesn\'t update all the posts.', 'vg_sheet_editor' ); ?>">(
									? )</a></label>
						</li>
					</template>
				<?php } ?>
				<?php do_action( 'vg_sheet_editor/formulas/after_form_fields', $current_post_type ); ?>
				<li class="rows-to-be-updated-total">
					<?php printf( __( '%s rows will be edited.', 'vg_sheet_editor' ), '<span class="total-count" x-text="totalSelectedRows"></span>' ); ?>
					<a href="#" data-wpse-tooltip="right"
						aria-label="<?php _e( 'You can select the rows for the edit in the first option of this form.', 'vg_sheet_editor' ); ?>">(
						? )</a>
				</li>
				<li>

					<button type="submit" class="remodal-confirm submit">
						<?php
						if ( is_admin() && VGSE()->helpers->user_can_manage_options() ) {
							_e( 'I have a database backup, Execute Now', 'vg_sheet_editor' );
						} else {
							_e( 'Execute Now', 'vg_sheet_editor' );
						}
						?>
					</button>
					<button data-remodal-action="confirm"
						class="remodal-cancel"><?php _e( 'Cancel', 'vg_sheet_editor' ); ?></button>
					<br />
					<?php if ( is_admin() && VGSE()->helpers->user_can_manage_options() && empty( VGSE()->options['enable_simple_mode'] ) ) { ?>
					<div class="alert alert-blue backup-alert" x-show="showBackupAlert()">
						<?php _e( '<p>1- Please backup your database before executing, the changes are not reversible.</p><p>2- Make sure the bulk edit settings are correct before executing.</p>', 'vg_sheet_editor' ); ?>
					</div>
					<?php } else { ?>
					<div class="alert alert-blue backup-alert" x-show="showBackupAlert()">
						<?php _e( 'Careful. The changes are not reversible. Please double check proceeding.', 'vg_sheet_editor' ); ?>
					</div>
					<?php } ?>
				</li>
			</ul>
		</form>
		<div class="vgse-bulk-edit-in-progress" x-show="executionInProgress">
			<p class="edit-running" x-show="!executionStopped">
				<?php _e( 'The bulk edit is running. Please dont close this window until the process has finished.', 'vg_sheet_editor' ); ?>
			</p>
			<?php if ( is_admin() && VGSE()->helpers->user_can_manage_options() ) { ?>
			<p class="speed-tip" x-show="showSpeedTip">
				<?php printf( __( '<b>Tip:</b> The formula execution is too slow? <a href="%1$s" target="_blank">Save <b>more posts</b> per batch</a><br/>Are you getting errors when executing the formula? <a href="%2$s" target="_blank">Save <b>less posts</b> per batch</a>', 'vg_sheet_editor' ), esc_url( VGSE()->helpers->get_settings_page_url() ), esc_url( VGSE()->helpers->get_settings_page_url() ) ); ?>
			</p>
			<?php } ?>

			<p class="formula-run-controls" x-show="!executionStopped">
				<button @click.prevent="pauseJob" x-show="!isProcessPaused" type="button"
					class="button pause-formula-execution button-secondary" data-action="pause">
					<i class="fa fa-pause"></i> <?php _e( 'Pause', 'vg_sheet_editor' ); ?>
				</button>
				<button @click.prevent="resumeJob" x-show="isProcessPaused" type="button"
					class="button resume-formula-execution button-secondary" data-action="resume">
					<i class="fa fa-play"></i> <?php _e( 'Resume', 'vg_sheet_editor' ); ?>
				</button>
				<button @click.prevent="goBackToForm" class="button go-back-formula-execution button-secondary"
					data-action="go-back">
					<i class="fa fa-angle-left"></i> <?php _e( 'Go back', 'vg_sheet_editor' ); ?>
				</button>
			</p>
			<div class="be-response" :class="columns.length > 1 ? 'multiple-edits' : 'single-edit'">
				<div id="be-bulk-edit-nanobar-container" x-show="showNanobar"></div>
				<template x-for="(message, columnKey) in successMessages">
					<div x-html="message"></div>
				</template>
				<p x-show="executionResponse" x-html="executionResponse"></p>
			</div>
			<button x-show="isProcessPaused || executionStopped" data-remodal-action="confirm"
				class="remodal-cancel"><?php _e( 'Close', 'vg_sheet_editor' ); ?></button>
		</div>
	</div>
</div>
