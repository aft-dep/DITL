// Local shim to avoid bundling @presto-player/components runtime via webpack
// and instead rely on the already-enqueued module script from PHP.
export const defineCustomElements = () => undefined;
