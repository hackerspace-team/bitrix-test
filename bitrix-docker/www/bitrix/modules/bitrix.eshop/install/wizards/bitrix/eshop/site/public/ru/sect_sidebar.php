<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();?>
<?if ($APPLICATION->GetCurPage(true) == SITE_DIR."index.php"):
	if (\Bitrix\Main\ModuleManager::isModuleInstalled('search')):
	?>
<div class="mb-5">
	<?$APPLICATION->IncludeComponent("bitrix:search.title", "bootstrap_v4", array(
			"NUM_CATEGORIES" => "1",
			"TOP_COUNT" => "5",
			"CHECK_DATES" => "N",
			"SHOW_OTHERS" => "N",
			"PAGE" => SITE_DIR."catalog/",
			"CATEGORY_0_TITLE" => "Товары" ,
			"CATEGORY_0" => array(
				0 => "iblock_catalog",
			),
			"CATEGORY_0_iblock_catalog" => array(
				0 => "all",
			),
			"CATEGORY_OTHERS_TITLE" => "Прочее",
			"SHOW_INPUT" => "Y",
			"INPUT_ID" => "title-search-input",
			"CONTAINER_ID" => "search",
			"PRICE_CODE" => array(
				0 => "BASE",
			),
			"SHOW_PREVIEW" => "Y",
			"PREVIEW_WIDTH" => "75",
			"PREVIEW_HEIGHT" => "75",
			"CONVERT_CURRENCY" => "Y"
		),
		false
	);?>
</div>
<?php
	endif;
endif;
?>

<div class="mb-5">
	<h3>Мы в соцсетях</h3>
	<?$APPLICATION->IncludeComponent(
		"bitrix:main.include",
		"",
		Array(
			"AREA_FILE_SHOW" => "file",
			"PATH" => SITE_DIR."include/socnet_sidebar.php",
			"AREA_FILE_RECURSIVE" => "N",
			"EDIT_MODE" => "html",
		),
		false,
		Array('HIDE_ICONS' => 'Y')
	);?>
</div>

<div class="mb-5 d-block d-sm-none">
	<?$APPLICATION->IncludeComponent(
		"bitrix:main.include",
		"",
		Array(
			"AREA_FILE_SHOW" => "file",
			"PATH" => SITE_DIR."include/sender.php",
			"AREA_FILE_RECURSIVE" => "N",
			"EDIT_MODE" => "html",
		),
		false,
		Array('HIDE_ICONS' => 'Y')
	);?>
</div>

<div class="mb-5">
	<?$APPLICATION->IncludeComponent(
		"bitrix:main.include",
		"",
		Array(
			"AREA_FILE_SHOW" => "file",
			"PATH" => SITE_DIR."include/about.php",
			"AREA_FILE_RECURSIVE" => "N",
			"EDIT_MODE" => "html",
		),
		false,
		Array('HIDE_ICONS' => 'N')
	);?>
</div>

<div class="mb-5">
	<?$APPLICATION->IncludeComponent(
		"bitrix:main.include",
		"",
		Array(
			"AREA_FILE_SHOW" => "file",
			"PATH" => SITE_DIR."include/twitter.php",
			"AREA_FILE_RECURSIVE" => "N",
			"EDIT_MODE" => "html",
		),
		false,
		Array('HIDE_ICONS' => 'N')
	);?>
</div>

<div class="mb-5">
	<?$APPLICATION->IncludeComponent(
		"bitrix:main.include",
		"",
		Array(
			"AREA_FILE_SHOW" => "file",
			"PATH" => SITE_DIR."include/info.php",
			"AREA_FILE_RECURSIVE" => "N",
			"EDIT_MODE" => "html",
		),
		false,
		Array('HIDE_ICONS' => 'N')
	);?>
</div>