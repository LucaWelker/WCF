<input type="checkbox" id="{$option->optionName}" {if $value} checked="checked"{/if} name="values[{$option->optionName}]" value="1" {if $disableOptions || $enableOptions}class="enablesOptions" data-disableOptions="[ {@$disableOptions}]" data-enableOptions="[ {@$enableOptions}]" {/if} />
