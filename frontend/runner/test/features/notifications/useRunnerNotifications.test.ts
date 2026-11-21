
    it('keeps the latest notification page when requests finish out of order', async () => {
        let resolveFirst!: (value: Awaited<ReturnType<typeof runnerApi.getNotifications>>) => void;
        let resolveSecond!: (value: Awaited<ReturnType<typeof runnerApi.getNotifications>>) => void;

        const firstRequest = new Promise<Awaited<ReturnType<typeof runnerApi.getNotifications>>>(
            (resolve) => {
                resolveFirst = resolve;
            },
        );

        const secondRequest = new Promise<Awaited<ReturnType<typeof runnerApi.getNotifications>>>(
            (resolve) => {
                resolveSecond = resolve;
            },
        );

        vi.mocked(runnerApi.getNotifications)
            .mockReturnValueOnce(firstRequest)
            .mockReturnValueOnce(secondRequest);

        const { result } = renderHook(() => useRunnerNotifications());

        await act(async () => {
            await result.current.loadNotifications(2);
        });

        await act(async () => {
            resolveSecond({
                notifications: [notifications[1]],
                pagination: {
                    ...pagination,
                    current_page: 2,
                },
                summary: {
                    unread_total: 0,
                },
            });
        });

        expect(result.current.notifications).toEqual([notifications[1]]);
        expect(result.current.notificationsPagination.current_page).toBe(2);

        await act(async () => {
            resolveFirst({
                notifications: [notifications[0]],
                pagination,
                summary: {
                    unread_total: 1,
                },
            });
        });

        expect(result.current.notifications).toEqual([notifications[1]]);
        expect(result.current.notificationsPagination.current_page).toBe(2);
        expect(result.current.unreadTotal).toBe(0);
    });

    it('ignores notification responses after the hook unmounts', async () => {
        let resolveRequest!: (value: Awaited<ReturnType<typeof runnerApi.getNotifications>>) => void;

        const pendingRequest = new Promise<Awaited<ReturnType<typeof runnerApi.getNotifications>>>(
            (resolve) => {
                resolveRequest = resolve;
            },
        );

        vi.mocked(runnerApi.getNotifications).mockReturnValueOnce(
            pendingRequest,
        );

        const { unmount } = renderHook(() => useRunnerNotifications());

        unmount();

        await act(async () => {
            resolveRequest({
                notifications,
                pagination,
                summary: {
                    unread_total: 1,
                },
            });

            await pendingRequest;
        });

        expect(runnerApi.getNotifications).toHaveBeenCalledOnce();
    });
