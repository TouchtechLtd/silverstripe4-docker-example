/* global jest, jasmine, describe, it, expect, beforeEach */

jest.unmock('deep-freeze-strict');
jest.unmock('../QueuedFilesActionTypes');
jest.unmock('../QueuedFilesReducer.js');

import queuedFilesReducer from '../QueuedFilesReducer.js';

describe('queuedFilesReducer', () => {
  describe('PURGE_UPLOAD_QUEUE', () => {
    const action = { type: 'PURGE_UPLOAD_QUEUE', payload: null };

    it('should remove failed uploads from the queue', () => {
      const initialState = {
        items: [
          {
            id: 0,
            message: {
              value: 'Failed to upload file',
              type: 'error',
              extraClass: 'error',
            },
          },
          {
            id: 1,
            message: {
              value: 'Uploading...',
              type: 'pending',
              extraClass: 'pending',
            },
          },
        ],
      };

      const nextState = queuedFilesReducer(initialState, action);

      expect(nextState.items.length).toBe(1);
    });

    it('should move successful uploads from the queue', () => {
      const initialState = {
        items: [
          {
            id: 0,
            message: {
              value: 'File uploaded',
              type: 'success',
              extraClass: 'success',
            },
          },
          {
            id: 1,
            message: {
              value: 'Uploading...',
              type: 'pending',
              extraClass: 'pending',
            },
          },
        ],
      };

      const nextState = queuedFilesReducer(initialState, action);

      expect(nextState.items.length).toBe(1);
    });

    it('should ignore pending uploads', () => {
      const initialState = {
        items: [
          {
            id: 0,
            message: {
              value: 'Uploading...',
              type: 'pending',
              extraClass: 'pending',
            },
          },
          {
            id: 1,
            message: {
              value: 'Uploading...',
              type: 'pending',
              extraClass: 'pending',
            },
          },
        ],
      };

      const nextState = queuedFilesReducer(initialState, action);

      expect(nextState.items.length).toBe(2);
    });
  });

  describe('FAIL_UPLOAD', () => {
    const type = 'FAIL_UPLOAD';
    const initialState = {
      items: [{
        filename: 'unclepaul.png',
        size: 123,
      }],
    };

    it('should set an error message', () => {
      const nextState = queuedFilesReducer(initialState, {
        type,
        payload: {
          message: {
            value: 'There was a problem.',
            type: 'error',
          },
        },
      });

      expect(nextState.items[0].message.type).toBe('error');
    });
  });

  describe('REMOVE_QUEUED_FILE', () => {
    const type = 'REMOVE_QUEUED_FILE';
    const initialState = {
      items: [{ queuedAtTime: 123 }, { queuedAtTime: 456 }],
    };

    it('should remove the file from the queue', () => {
      const nextState = queuedFilesReducer(initialState, {
        type,
        payload: { queuedAtTime: 456 },
      });

      expect(nextState.items.length).toBe(1);
      expect(nextState.items[0].queuedAtTime).toBe(123);
    });
  });
});
