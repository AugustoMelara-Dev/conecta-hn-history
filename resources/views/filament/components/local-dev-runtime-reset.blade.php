<script>
(() => {
    const host = window.location.hostname;
    const isLocalHost = host === '127.0.0.1' || host === 'localhost';

    if (!isLocalHost || !window.sessionStorage) {
        return;
    }

    const runtimeResetKey = 'filament-local-runtime-reset-v17';

    window.addEventListener('pageshow', (event) => {
        if (event.persisted) {
            window.location.reload();
        }
    });

    if (window.sessionStorage.getItem(runtimeResetKey) === 'done') {
        return;
    }

    const cleanupTasks = [];
    let shouldReload = false;

    if ('serviceWorker' in navigator) {
        cleanupTasks.push(
            navigator.serviceWorker.getRegistrations().then((registrations) => {
                if (registrations.length > 0) {
                    shouldReload = true;
                }

                return Promise.allSettled(
                    registrations.map((registration) => registration.unregister()),
                );
            }),
        );
    }

    if ('caches' in window) {
        cleanupTasks.push(
            caches.keys().then((keys) => {
                if (keys.length > 0) {
                    shouldReload = true;
                }

                return Promise.allSettled(keys.map((key) => caches.delete(key)));
            }),
        );
    }

    if ('indexedDB' in window) {
        cleanupTasks.push(
            Promise.allSettled([
                indexedDB.databases
                    ? indexedDB.databases().then((databases) => {
                          const knownNames = databases
                              .map((database) => database.name)
                              .filter((name) =>
                                  typeof name === 'string' &&
                                  (
                                      name.includes('workbox') ||
                                      name.includes('filament') ||
                                      name.includes('livewire') ||
                                      name.includes('vite')
                                  )
                              );

                          if (knownNames.length > 0) {
                              shouldReload = true;
                          }

                          return Promise.allSettled(
                              knownNames.map((name) => indexedDB.deleteDatabase(name)),
                          );
                      })
                    : Promise.resolve(),
            ]),
        );
    }

    try {
        if (window.sessionStorage.length > 0) {
            shouldReload = true;
        }

        window.sessionStorage.clear();
    } catch (error) {
        console.warn('No se pudo limpiar sessionStorage local.', error);
    }

    try {
        if (window.localStorage.length > 0) {
            shouldReload = true;
        }

        window.localStorage.clear();
    } catch (error) {
        console.warn('No se pudo limpiar localStorage local.', error);
    }

    Promise.allSettled(cleanupTasks).finally(() => {
        window.sessionStorage.setItem(runtimeResetKey, 'done');

        if (shouldReload) {
            window.setTimeout(() => window.location.reload(), 60);
        }
    });
})();
</script>
