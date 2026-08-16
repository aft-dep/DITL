declare const _default: {
    nullOrUndefined: (input: any) => boolean;
    object: (input: any) => boolean;
    string: (input: any) => boolean;
    nodeList: (input: any) => boolean;
    element: (input: any) => boolean;
    empty: (input: any) => boolean;
    array: (input: any) => input is any[];
};
export default _default;
