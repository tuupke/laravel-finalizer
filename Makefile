# Runs the test suites in Docker against every supported PHP/Laravel combination.
# Run a single combination with e.g. `make test PHP=8.5 LARAVEL=13`.

MATRIX ?= 7.4:6 7.4:7 7.4:8 8.0:9 8.1:10 8.2:11 8.3:12 8.4:12 8.5:12 8.5:13

ifneq ($(PHP)$(LARAVEL),)
MATRIX := $(PHP):$(LARAVEL)
endif

.PHONY: test test-cli test-http clean

test:
	@bash tests/run.sh all $(MATRIX)

test-cli:
	@bash tests/run.sh cli $(MATRIX)

test-http:
	@bash tests/run.sh http $(MATRIX)

clean:
	rm -rf .build
