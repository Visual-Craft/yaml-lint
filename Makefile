APP_NAME := yaml-lint

DIR := $(realpath $(dir $(realpath $(MAKEFILE_LIST))))
DIST := $(DIR)/dist
BUILD := $(DIR)/.build
TOOLS_DIR := $(BUILD)/bin

COMPOSER_BIN := $(TOOLS_DIR)/composer
PHAR_COMPOSER_BIN := $(TOOLS_DIR)/phar-composer

ifeq ($(SF_VER),)
SF_VER = 4.1
endif

PHAR := $(DIST)/$(APP_NAME)-sf$(SF_VER).phar

SOURCES := $(shell find $(DIR)/src -name '*.php')
SOURCES += $(DIR)/bin/yaml-lint


.PHONY: build
build: $(PHAR)

.PHONY: build-all
build-all:
	SF_VER="4.1"   $(MAKE) build
	SF_VER="3.4.0" $(MAKE) build
	SF_VER="3.3.0" $(MAKE) build
	SF_VER="2.8"   $(MAKE) build

$(PHAR): $(COMPOSER_BIN) $(PHAR_COMPOSER_BIN) $(DIR)/composer.json $(SOURCES)
	rm -rf $(BUILD)/files
	mkdir -p $(BUILD)/files
	cp -r $(DIR)/src $(DIR)/bin $(DIR)/composer.json $(BUILD)/files
	cd $(BUILD)/files && $(COMPOSER_BIN) require --update-no-dev --no-interaction --optimize-autoloader \
		"symfony/console:~$(SF_VER)" \
		"symfony/yaml:~$(SF_VER)" \
		"symfony/debug:~$(SF_VER)"
	find $(BUILD)/files/vendor -name Tests -type d -exec rm -rf {} +
	find $(BUILD)/files/vendor -name .gitignore -type f -exec rm {} +
	find $(BUILD)/files/vendor -name composer.json -type f -exec rm {} +
	find $(BUILD)/files/vendor -name phpunit.xml.dist -type f -exec rm {} +
	find $(BUILD)/files/vendor -name LICENSE -type f -exec rm {} +
	find $(BUILD)/files/vendor -name '*.md' -type f -exec rm {} +
	rm -rf $(BUILD)/files/vendor/symfony/debug/Resources/ext
	$(PHAR_COMPOSER_BIN) build $(BUILD)/files $(PHAR)
	rm -rf $(BUILD)/files

$(COMPOSER_BIN) $(PHAR_COMPOSER_BIN):
	mkdir -p $(TOOLS_DIR)
	wget "https://getcomposer.org/download/1.7.2/composer.phar" -O $(COMPOSER_BIN)
	wget "https://github.com/clue/phar-composer/releases/download/v1.0.0/phar-composer.phar" -O $(PHAR_COMPOSER_BIN)
	chmod +x $(COMPOSER_BIN) $(PHAR_COMPOSER_BIN)
