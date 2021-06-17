APP_NAME := vc-yaml-lint

DIR := $(realpath $(dir $(realpath $(MAKEFILE_LIST))))
DIST := $(DIR)/dist
BUILD := $(DIR)/.build
TOOLS_DIR := $(BUILD)/bin
BUILD_FILES := $(BUILD)/files

COMPOSER_BIN := $(TOOLS_DIR)/composer
PHAR_COMPOSER_BIN := $(TOOLS_DIR)/phar-composer

ifeq ($(SF_VER),)
SF_VER = 4.1
endif

PHAR := $(DIST)/$(APP_NAME)-sf$(SF_VER).phar

SOURCES := $(shell find $(DIR)/src -name '*.php')
SOURCES += $(DIR)/bin/vc-yaml-lint


.PHONY: build
build: $(PHAR)

.PHONY: build-all
build-all:
	SF_VER="4.1"   $(MAKE) build
	SF_VER="3.4.0" $(MAKE) build
	SF_VER="3.3.0" $(MAKE) build
	SF_VER="2.8"   $(MAKE) build

.PHONY: clean
clean:
	rm -rf $(DIST) $(BUILD_FILES)

$(PHAR): $(DIR)/composer.json $(SOURCES) | $(DIST) $(COMPOSER_BIN) $(PHAR_COMPOSER_BIN)
	rm -rf $(BUILD_FILES)
	mkdir -p $(BUILD_FILES)
	cp -r $(DIR)/src $(DIR)/bin $(DIR)/composer.json $(BUILD_FILES)
	cd $(BUILD_FILES) && $(COMPOSER_BIN) require --update-no-dev --no-interaction --classmap-authoritative --ignore-platform-reqs \
		"symfony/console:~$(SF_VER)" \
		"symfony/yaml:~$(SF_VER)" \
		"symfony/debug:~$(SF_VER)"
	find $(BUILD_FILES)/vendor -name Tests -type d -exec rm -rf {} +
	find $(BUILD_FILES)/vendor -name .gitignore -type f -exec rm {} +
	find $(BUILD_FILES)/vendor -name composer.json -type f -exec rm {} +
	find $(BUILD_FILES)/vendor -name phpunit.xml.dist -type f -exec rm {} +
	find $(BUILD_FILES)/vendor -name LICENSE -type f -exec rm {} +
	find $(BUILD_FILES)/vendor -name '*.md' -type f -exec rm {} +
	rm -rf $(BUILD_FILES)/vendor/symfony/debug/Resources/ext
	$(PHAR_COMPOSER_BIN) build $(BUILD_FILES) $(PHAR)
	rm -rf $(BUILD_FILES)

$(COMPOSER_BIN) $(PHAR_COMPOSER_BIN):
	mkdir -p $(TOOLS_DIR)
	wget "https://getcomposer.org/download/2.1.3/composer.phar" -O $(COMPOSER_BIN)
	wget "https://github.com/clue/phar-composer/releases/download/v1.2.0/phar-composer-1.2.0.phar" -O $(PHAR_COMPOSER_BIN)
	chmod +x $(COMPOSER_BIN) $(PHAR_COMPOSER_BIN)

$(DIST):
	mkdir $@
	touch $@
